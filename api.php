<?php
// 1. Database Configuration
$host = '127.0.0.1';
$db = 'weeb_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];


// 2. Configure API headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

//connect to the anime API


// 3. Connect to the database
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// 4. Identify the HTTP method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


// Base path: /Web-Services/api.php
$apiPosition = strpos($uri, '/Web-Services/api.php');

if ($apiPosition === false) {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint not found"]);
    exit;
}

$path = substr(
    $uri,
    $apiPosition + strlen('/Web-Services/api.php')
);

// 5. Define the allowed resources and fields
$resources = [
    'anime' => [
        'title',
        'season_aired',
        'year_aired',
        'number_of_episodes',
        'number_of_seasons',
        'studio',
        'genre',
        'status'
    ],
    'manga' => [
        'title',
        'author',
        'year_published',
        'number_of_volumes',
        'number_of_chapters',
        'genre',
        'status'
    ]
];

// 6. Match the requested endpoint (filter)
if (!preg_match('#^/(anime|manga)(?:/([0-9]+))?/?$#', $path, $matches)) {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint not found"]);
    exit;
}

$table = $matches[1];
$id = isset($matches[2]) && $matches[2] !== ''
    ? (int) $matches[2]
    : null;

$allowedFields = $resources[$table];

// 7. GET all records or POST a new record
if ($id === null) {

    // GET all records
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY id ASC");
        $records = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode($records);
        exit;
    }

    // POST a new record
    if ($method === 'POST') {

        $rawJson = file_get_contents('php://input');
        $input = json_decode($rawJson, true);

        // Validate JSON object
        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($input) ||
            array_is_list($input)
        ) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON object"]);
            exit;
        }

        // Title is required
        if (
            !isset($input['title']) ||
            !is_string($input['title']) ||
            trim($input['title']) === ''
        ) {
            http_response_code(400);
            echo json_encode([
                "error" => "A non-empty title is required"
            ]);
            exit;
        }

        // Reject unknown fields
        $unknownFields = array_diff(
            array_keys($input),
            $allowedFields
        );

        if (!empty($unknownFields)) {
            http_response_code(400);
            echo json_encode(["error" => "Unknown field provided"]);
            exit;
        }

        // Build the INSERT query using supplied fields only
        $fields = array_keys($input);
        $columns = implode(
            ', ',
            array_map(fn($field) => "`$field`", $fields)
        );
        $placeholders = implode(
            ', ',
            array_map(fn($field) => ":$field", $fields)
        );

        $sql = "INSERT INTO `$table` ($columns)
                VALUES ($placeholders)";

        $stmt = $pdo->prepare($sql);

        foreach ($input as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }

        $stmt->execute();

        // Return the newly created record
        $newId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "SELECT * FROM `$table` WHERE id = :id"
        );
        $stmt->execute([':id' => $newId]);

        http_response_code(201);
        echo json_encode($stmt->fetch());
        exit;
    }

    http_response_code(405);
    header("Allow: GET, POST, OPTIONS");
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// 8. GET, PUT, PATCH, or DELETE one record
$stmt = $pdo->prepare(
    "SELECT * FROM `$table` WHERE id = :id"
);
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();

// GET one record
if ($method === 'GET') {
    if (!$record) {
        http_response_code(404);
        echo json_encode(["error" => "Record not found"]);
        exit;
    }

    http_response_code(200);
    echo json_encode($record);
    exit;
}

// Check whether the record exists for modification
if (!$record) {
    http_response_code(404);
    echo json_encode(["error" => "Record not found"]);
    exit;
}

// DELETE one record
if ($method === 'DELETE') {
    $stmt = $pdo->prepare(
        "DELETE FROM `$table` WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);

    http_response_code(200);
    echo json_encode(["message" => "Record deleted successfully"]);
    exit;
}

// PUT or PATCH an existing record
if ($method === 'PUT' || $method === 'PATCH') {

    $rawJson = file_get_contents('php://input');
    $input = json_decode($rawJson, true);

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($input) ||
        array_is_list($input)
    ) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON object"]);
        exit;
    }

    // Reject unknown fields
    $unknownFields = array_diff(
        array_keys($input),
        $allowedFields
    );

    if (!empty($unknownFields)) {
        http_response_code(400);
        echo json_encode(["error" => "Unknown field provided"]);
        exit;
    }

    // PUT replaces the supplied resource fields and requires a title.
    if ($method === 'PUT') {
        if (
            !isset($input['title']) ||
            !is_string($input['title']) ||
            trim($input['title']) === ''
        ) {
            http_response_code(400);
            echo json_encode([
                "error" => "A non-empty title is required"
            ]);
            exit;
        }

        // Missing allowed fields become NULL on replacement.
        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $input)) {
                $input[$field] = null;
            }
        }
    }

    if (empty($input)) {
        http_response_code(400);
        echo json_encode(["error" => "No fields provided"]);
        exit;
    }

    // Validate title whenever it is supplied
    if (
        array_key_exists('title', $input) &&
        (!is_string($input['title']) ||
         trim($input['title']) === '')
    ) {
        http_response_code(400);
        echo json_encode([
            "error" => "Title cannot be empty"
        ]);
        exit;
    }

    // Build the UPDATE query
    $setParts = [];

    foreach (array_keys($input) as $field) {
        $setParts[] = "`$field` = :$field";
    }

    $sql = "UPDATE `$table`
            SET " . implode(', ', $setParts) . "
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    foreach ($input as $field => $value) {
        $stmt->bindValue(":$field", $value);
    }

    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Return the updated record
    $stmt = $pdo->prepare(
        "SELECT * FROM `$table` WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);

    http_response_code(200);
    echo json_encode($stmt->fetch());
    exit;
}

// Unsupported method
http_response_code(405);
header("Allow: GET, PUT, PATCH, DELETE, OPTIONS");
echo json_encode(["error" => "Method not allowed"]);
exit;

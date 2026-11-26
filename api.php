<?php
// Never show PHP errors, warnings or notices to visitors: they go to the PHP error log
// instead (log_errors), whatever php.ini says. Remove these lines only for local debugging.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// Anything not caught below: log it and answer with a generic JSON error, so clients
// never get an empty body or PHP's own error text.
set_exception_handler(function (Throwable $e): void {
    error_log('api.php: ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(["error" => "Something went wrong. Please try again."]);
});

// 1. Database Configuration

$envPath = __DIR__ . '/.env';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    $host = $env['host'];
    $port = $env['port'];
    $database = $env['database'];
    $user = $env['user'];
    $password = $env['password'];
} else {
    error_log('api.php: .env file not found in ' . __DIR__);
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(["error" => "Server configuration error."]);
    exit;
}
$dsn = "pgsql:host=$host;port=$port;dbname=$database";

function sendJson(int $status, mixed $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// Never send password hashes out of the API, not even to admins.
function withoutSecrets(mixed $row): mixed
{
    if (is_array($row)) {
        unset($row['password']);
    }
    return $row;
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // true = PHP prepares the queries itself. Supabase's connection pooler can run a query on a
    // different connection than the one it was prepared on, which randomly fails with
    // 'prepared statement "pdo_stmt_..." does not exist'. Values are still escaped safely.
    PDO::ATTR_EMULATE_PREPARES => true,
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
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// 4. Identify the HTTP method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


// Base path: /Weeb-ify/api.php under Apache, or /api.php under php -S -t public
$apiPosition = strpos($uri, '/api.php');

if ($apiPosition === false) {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint not found"]);
    exit;
}

$path = substr(
    $uri,
    $apiPosition + strlen('/api.php')
);


// 5. Define the allowed resources and fields
// anime / manga: the snake_case columns from app/config/catalogue-fields.php (shared with the admin page)
$catalogueFields = require __DIR__ . '/app/config/catalogue-fields.php';
$resources = [
    'anime' => $catalogueFields['anime']['fields'],
    'favorites'=>[
              'user_id',
            'anime_id' 
        ],
    'manga' => $catalogueFields['manga']['fields'],
    // /user is admin-only (see below) and read-only through the API: no writable fields
    'user' => [],
];

// 6. Match the requested endpoint (filter)
if (!preg_match('#^/(anime|manga|user|favorites)(?:/([0-9]+))?/?$#', $path, $matches)) {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint not found"]);
    exit;
}

$table = $matches[1];
$id = isset($matches[2]) && $matches[2] !== ''
    ? (int) $matches[2]
    : null;

$allowedFields = $resources[$table];

// Admin only (a logged-in user (401) whose user.is_admin is true (403)):
//   - anime / manga writes. Reads of the catalogue stay public.
//   - everything on /user (it holds emails and password hashes), reads included.
// is_admin is read from the database on every request, so removing someone's admin flag
// takes effect immediately. The session key is the one AuthController::login() sets.
$catalogueWrite = in_array($table, ['anime', 'manga'], true)
    && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
if ($catalogueWrite || $table === 'user') {
    $adminUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    if ($adminUserId === null) {
        sendJson(401, ["error" => "Login required"]);
    }
    $adminStmt = $pdo->prepare('SELECT is_admin FROM public."user" WHERE id = :id');
    $adminStmt->execute([':id' => $adminUserId]);
    if (!filter_var($adminStmt->fetchColumn(), FILTER_VALIDATE_BOOLEAN)) {
        sendJson(403, ["error" => "Admin access required"]);
    }
}

//========================================================
// Favorites hold one row per (user, target), where a target is either an anime or a
// manga. Only the column in the favorites table differs ("anime_id" / "manga_id"),
// so the verbs below are written once against $target.
//
// ?type=anime|manga picks the kind. When it is missing we fall back to whichever id
// was sent (?anime_id= / ?manga_id=), so older links keep working unchanged.
if ($table === 'favorites') {
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    if ($userId === null) {
        sendJson(401, ["error" => "Login required"]);
    }

    $type = is_string($_GET['type'] ?? null) ? strtolower(trim($_GET['type'])) : '';

    if ($type === '') {
        $type = array_key_exists('manga_id', $_GET) ? 'manga' : 'anime';
    }

    if (!in_array($type, ['anime', 'manga'], true)) {
        sendJson(400, ["error" => "Invalid type. Allowed: anime, manga"]);
    }

    $column = $type === 'manga' ? 'manga_id' : 'anime_id';

    $targetId = filter_var(
        $_GET[$column] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $targetId = ($targetId === false || $targetId === null) ? null : (int) $targetId;

    // ---- GET: list the current user's favorites of this type ----
    if ($method === 'GET') {
        if ($type === 'manga') {
            $stmt = $pdo->prepare("
                SELECT f.id, f.manga_id, f.created_at,
                       m.title, m.image,
                       m.chapter_count, m.volume_count,
                       m.manga_type, m.serialization, m.status
                FROM public.favorites f
                LEFT JOIN public.manga m ON m.manga_id = f.manga_id
                WHERE f.user_id = :uid AND f.manga_id IS NOT NULL
                ORDER BY f.created_at DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT f.id, f.anime_id, f.created_at,
                       a.title, a.image,
                       a.episode_count, a.show_type,
                       a.age_rating, a.popularity_rank, a.status
                FROM public.favorites f
                LEFT JOIN public.anime a ON a.anime_id = f.anime_id
                WHERE f.user_id = :uid AND f.anime_id IS NOT NULL
                ORDER BY f.created_at DESC
            ");
        }

        try {
            $stmt->execute([':uid' => $userId]);
            sendJson(200, $stmt->fetchAll());
        } catch (PDOException $e) {
            error_log("Favorites list failed ($type): " . $e->getMessage());
            sendJson(500, ["error" => "Could not load favorites."]);
        }
    }

    // ---- POST: add (idempotent) ----
    if ($method === 'POST') {
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body) || array_is_list($body)) {
            $body = [];
        }

        $postedId = filter_var(
            $body[$column] ?? $_GET[$column] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $postedId = ($postedId === false || $postedId === null) ? null : (int) $postedId;

        if ($postedId === null) {
            sendJson(400, ["error" => "A valid $column is required"]);
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO public.favorites (user_id, $column)
                 VALUES (:user_id, :target_id)
                 ON CONFLICT DO NOTHING"
            );
            $stmt->execute([':user_id' => $userId, ':target_id' => $postedId]);
        } catch (PDOException $e) {
            error_log("Favorites insert ($type $postedId) failed: " . $e->getMessage());

            [$status, $message] = match ((string) $e->getCode()) {
                '42703' => [500, "The favorites table has no $column column."],
                '23502' => [500, "The favorites table still requires $column."],
                '23503' => [404, "That $type does not exist."],
                '23505' => [409, "That $type is already in your favorites."],
                default => [400, "Could not save that favorite."],
            };

            sendJson($status, ["error" => $message, "sqlstate" => (string) $e->getCode()]);
        }

        sendJson(201, ["success" => true]);
    }

    // ---- DELETE: remove (idempotent) ----
    if ($method === 'DELETE') {
        if ($targetId === null) {
            sendJson(400, ["error" => "A valid $column is required"]);
        }

        $stmt = $pdo->prepare(
            "DELETE FROM public.favorites
              WHERE user_id = :user_id AND $column = :target_id"
        );
        $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);

        if ($stmt->rowCount() === 0) {
            sendJson(404, ["error" => "Favorite not found"]);
        }

        sendJson(200, ["success" => true]);
    }

    // PUT / PATCH make no sense for a (user, target) pair.
    header("Allow: GET, POST, DELETE, OPTIONS");
    sendJson(405, ["error" => "Method not allowed"]);
}

// 7. GET all records or POST a new record
if ($id === null) {

    // GET all anime and manga: filters, categories, sorting and cursor pagination.
    // Cursors are sent back in headers so the
    // response body stays a plain JSON array, the same as before.
    if ($method === 'GET' && in_array($table, ['anime', 'manga'], true)) {
        require_once __DIR__ . '/app/Services/AnimeListService.php';
        $filters = require __DIR__ . '/app/config/' . $table . '-filters.php';

        try {
            $result = (new App\Services\AnimeListService($pdo, $filters, $table))->list($_GET);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }

        header('Access-Control-Expose-Headers: X-Next-Cursor, X-Prev-Cursor');
        if ($result['next'] !== null) {
            header('X-Next-Cursor: ' . $result['next']);
        }
        if ($result['prev'] !== null) {
            header('X-Prev-Cursor: ' . $result['prev']);
        }

        http_response_code(200);
        echo json_encode($result['records']);
        exit;
    }

    // GET all records
    if ($method === 'GET') {
        $page = filter_var(
            $_GET['page'] ?? '1',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1000000]]
        );

        if ($page === false) {
            http_response_code(400);
            echo json_encode([
                "error" => "The page parameter must be an integer between 1 and 1000000"
            ]);
            exit;
        }

        $itemsPerPage = 20;
        $offset = ($page - 1) * $itemsPerPage;
        $searchQuery = isset($_GET['q']) && is_string($_GET['q'])
            ? trim($_GET['q'])
            : '';
        // The manga table has no author / genre columns, so search the title only
        $searchCondition = $table === 'manga' && $searchQuery !== ''
            ? " WHERE title ILIKE :search_title ESCAPE '!'"
            : '';

        $stmt = $pdo->prepare(
            "SELECT * FROM public.$table" . $searchCondition . "
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset"
        );
        if ($searchCondition !== '') {
            $searchTerm = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $searchQuery) . '%';
            $stmt->bindValue(':search_title', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode($table === 'user' ? array_map('withoutSecrets', $records) : $records);
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
        if($table!='user')
            {
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
        $columns = implode(', ', $fields);
        $placeholders = implode(
            ', ',
            array_map(fn($field) => ":$field", $fields)
        );

        $sql = "INSERT INTO public.$table ($columns)
                VALUES ($placeholders)
                RETURNING *";

        $stmt = $pdo->prepare($sql);

        foreach ($input as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }

        $stmt->execute();

        // Return the newly created record
        // $newId = (int) $pdo->lastInsertId();

        // $stmt = $pdo->prepare(
        //     "SELECT * FROM public.$table WHERE id = :id"
        // );
        // $stmt->execute([':id' => $newId]);

        http_response_code(201);
        echo json_encode(withoutSecrets($stmt->fetch()));
        exit;
    }

    http_response_code(405);
    header("Allow: GET, POST, OPTIONS");
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// 8. GET, PUT, PATCH, or DELETE one record
$stmt = $pdo->prepare(
    "SELECT * FROM public.$table WHERE id = :id"
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
    echo json_encode(withoutSecrets($record));
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
        "DELETE FROM public.$table WHERE id = :id"
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
        // Postgres quotes identifiers with "...", not MySQL backticks (fields are allowlisted)
        $setParts[] = "\"$field\" = :$field";
    }

    $sql = "UPDATE public.$table
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
        "SELECT * FROM public.$table WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);

    http_response_code(200);
    echo json_encode(withoutSecrets($stmt->fetch()));
    exit;
}



// Unsupported method
http_response_code(405);
header("Allow: GET, PUT, PATCH, DELETE, OPTIONS");
echo json_encode(["error" => "Method not allowed"]);
exit;

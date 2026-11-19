<?php
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
    die("Error: .env file not found.");
}
$dsn = "pgsql:host=$host;port=$port;dbname=$database";



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


// Base path: /Web-Services/api.php
$apiPosition = strpos($uri, '/Weeb-ify/api.php');

if ($apiPosition === false) {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint not found"]);
    exit;
}

$path = substr(
    $uri,
    $apiPosition + strlen('/Weeb-ify/api.php')
);

if (preg_match('#^/favorites/?$#', $path) === 1) {
    if ($method !== 'POST') {
        http_response_code(405);
        header("Allow: POST, OPTIONS");
        echo json_encode(["error" => "Method not allowed"]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || array_is_list($input)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON object"]);
        exit;
    }

    $userId = filter_var(
        $input['user_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $animeId = $input['anime_id'] ?? null;
    $unknownFields = array_diff(array_keys($input), ['user_id', 'anime_id']);

    if (
        $userId === false ||
        (!is_string($animeId) && !is_int($animeId)) ||
        trim((string) $animeId) === '' ||
        strlen((string) $animeId) > 255 ||
        $unknownFields !== []
    ) {
        http_response_code(400);
        echo json_encode(["error" => "A valid user_id and anime_id are required"]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO public.favorites (user_id, anime_id)
         VALUES (:user_id, :anime_id)
         ON CONFLICT (user_id, anime_id) DO NOTHING
         RETURNING *"
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':anime_id' => trim((string) $animeId)
    ]);
    $favorite = $stmt->fetch();

    if ($favorite === false) {
        http_response_code(200);
        echo json_encode(["message" => "This anime is already in your favorites"]);
        exit;
    }

    http_response_code(201);
    echo json_encode($favorite);
    exit;
}

// 5. Define the allowed resources and fields
$resources = [
    'anime' => [
        'anime_id',
        'slug',
        'image',
        'title',
        'synopsis',
        'agerating',
        'createdat',
        'enddate',
        'episodecount',
        'popularityrank',
        'showtype',
        'status',
        'kitsu_url'
        ],  
    'favorites'=>[
              'user_id',
            'anime_id' 
        ],
     'manga' => [
        'manga_id',
        'slug',
        'image',
        'title',
        'synopsis',
        'agerating',
        'createdat',
        'enddate',
        'chaptercount',
        'volumecount',
        'popularityrank',
        'mangatype',
        'serialization',
        'status',
        'kitsu_url',
    ]
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

//========================================================
//the regular handler would not work for the favorites table since we're doing a joins 
if ($table === 'favorites') {
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    if ($userId === null) {
        http_response_code(401);
        echo json_encode(["error" => "Login required"]);
        exit;
    }

    
    // Read the JSON body once, for the verbs that carry one.
    $body = [];
    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
    }
    // ---- Which kind of favorite is this request about? ----
    $type = is_string($_GET['type'] ?? null) ? strtolower(trim($_GET['type'])) : '';
    if ($type === '') {
        $type = array_key_exists('manga_id', $_GET) || array_key_exists('manga_id', $body)
            ? 'manga'
            : 'anime';
    }
    if (!in_array($type, ['anime', 'manga'], true)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid type. Allowed: anime, manga"]);
        exit;
    }
    // Everything that differs between the two kinds lives in this config.
    $target = $type === 'manga'
        ? [
            'column' => 'manga_id',
            'table'  => 'public.manga',
            'alias'  => 'm',
            'detail' => 'm.id, m.title, m.image, m.chaptercount AS chapter_count, '
                      . 'm.volumecount AS volume_count, m.mangatype AS manga_type, '
                      . 'm.serialization, m.status, m.agerating AS age_rating, '
                      . 'm.popularityrank AS popularity_rank',
        ]
        : [
            'column' => 'anime_id',
            'table'  => 'public.anime',
            'alias'  => 'a',
            'detail' => 'a.id, a.title, a.image, a.episode_count, '
                      . 'a.status, a.show_type, a.age_rating, a.popularity_rank',
        ];
    $column = $target['column'];
    $alias  = $target['alias'];
    // The id in question, accepted from the query string or from the JSON body.
    $targetId = filter_var(
        $_GET[$column] ?? $body[$column] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $targetId = ($targetId === false || $targetId === null) ? null : (int) $targetId;
    // ---- GET: "is this one a favorite?" when an id is given, otherwise the list ----
    if ($method === 'GET') {
        if ($targetId !== null) {
            $stmt = $pdo->prepare(
                "SELECT $column FROM public.favorites
                  WHERE user_id = :user_id AND $column = :target_id
                  LIMIT 1"
            );
            $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);
            echo json_encode(["isFavorite" => (bool) $stmt->fetch()]);
            exit;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT f.$column, {$target['detail']}
                   FROM public.favorites f
                   JOIN {$target['table']} $alias ON $alias.$column = f.$column
                  WHERE f.user_id = :user_id
                  ORDER BY f.created_at DESC"
            );
            $stmt->execute([':user_id' => $userId]);
            $records = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Favorites list ($type) failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Could not load favorites."]);
            exit;
        }
        echo json_encode($records);
        exit;
    }
    // ---- POST: add it (idempotent, so a double press is not an error) ----
    if ($method === 'POST') {
        if ($targetId === null) {
            http_response_code(400);
            echo json_encode(["error" => "A valid $column is required"]);
            exit;
        }
        try {
            // No ON CONFLICT target: this works whether uniqueness comes from the plain
            // (user_id, anime_id) key or from the partial manga index.
            $stmt = $pdo->prepare(
                "INSERT INTO public.favorites (user_id, $column)
                 VALUES (:user_id, :target_id)
                 ON CONFLICT DO NOTHING"
            );
            $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);
        } catch (PDOException $e) {
            // The CHECK (one target per row) and the foreign key both land here,
            // so a bad or unknown id is a 400 instead of a 500.
            error_log("Favorites insert ($type $targetId) failed: " . $e->getMessage());
            http_response_code(400);
            echo json_encode(["error" => "That $column could not be favorited."]);
            exit;
        }
        http_response_code(201);
        echo json_encode(["success" => true]);
        exit;
    }
    // ---- DELETE: remove it (also idempotent) ----
    if ($method === 'DELETE') {
        if ($targetId === null) {
            http_response_code(400);
            echo json_encode(["error" => "A valid $column is required"]);
            exit;
        }
        $stmt = $pdo->prepare(
            "DELETE FROM public.favorites
              WHERE user_id = :user_id AND $column = :target_id"
        );
        $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);
        echo json_encode(["success" => true]);
        exit;
    }
}

// 7. GET all records or POST a new record
if ($id === null) {

    // GET all anime: filters, categories, sorting and cursor pagination
    // (see app/Services/AnimeListService.php). Cursors are sent back in headers so the
    // response body stays a plain JSON array, the same as before.
    if ($method === 'GET' && $table === 'anime') {
        require_once __DIR__ . '/app/Services/AnimeListService.php';
        $animeFilters = require __DIR__ . '/app/config/anime-filters.php';

        try {
            $result = (new App\Services\AnimeListService($pdo, $animeFilters))->list($_GET);
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
        $searchCondition = $table === 'manga' && $searchQuery !== ''
            ? ' WHERE (title ILIKE :search_title OR author ILIKE :search_author OR genre ILIKE :search_genre)'
            : '';

        $stmt = $pdo->prepare(
            "SELECT * FROM public.$table" . $searchCondition . "
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset"
        );
        if ($searchCondition !== '') {
            $searchTerm = '%' . $searchQuery . '%';
            $stmt->bindValue(':search_title', $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(':search_author', $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(':search_genre', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
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
        $setParts[] = "`$field` = :$field";
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
    echo json_encode($stmt->fetch());
    exit;
}



// Unsupported method
http_response_code(405);
header("Allow: GET, PUT, PATCH, DELETE, OPTIONS");
echo json_encode(["error" => "Method not allowed"]);
exit;

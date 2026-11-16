<?php

//connect to the database
//add like 500 animes to the database lmao

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type");

try {
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    $host = $env['host'];
    $port = $env['port'];
    $database = $env['database'];
    $user = $env['user'];
    $password = $env['password'];
} else {
    // Proper error status + JSON instead of die(); details go to the PHP error log only
    error_log('db.php: .env file not found at ' . $envPath);
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit(1);
}
$dsn = "pgsql:host=$host;port=$port;dbname=$database";

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
} catch (PDOException $e) {
    // The exception message can contain the host and username, so never print it
    error_log('db.php: database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit(1);
}

$ch = "https://kitsu.io/api/edge/anime";
$page = 20;
$fetch = 800;

// The anime table is created by database/schema.sql (run that first).
// Column names: see schema.sql (age_rating, show_type, ...).

//test to see if there is data in the db

$sql = "SELECT id FROM anime WHERE id = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    echo json_encode(['message' => 'Data already exists in the database.']);
    exit;
}

$sql = "INSERT INTO anime (anime_id, slug, image, title, synopsis, age_rating, created_at, end_date, episode_count, popularity_rank, show_type, status, kitsu_url)
        VALUES (:anime_id, :slug, :image, :title, :synopsis, :ageRating, :createdAt, :endDate, :episodeCount, :popularityRank, :showType, :status, :kitsu_url)";


$stmt = $pdo->prepare($sql);



for($i = 0; $i < $fetch; $i += $page) {
    $url = "$ch?page[limit]=$page&page[offset]=$i";
    $curlHandle = curl_init($url);
    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
    $anime_ = curl_exec($curlHandle);
    $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($curlHandle);
    unset($curlHandle);

    if ($anime_ === FALSE) {
        error_log("db.php: Kitsu request failed ($url): $curlErr");
        http_response_code(502);
        echo json_encode(['error' => 'Could not reach the Kitsu API']);
        exit(1);
    }

    $response = json_decode($anime_, true);
    $data = $response['data'] ?? [];

    foreach ($data as $anime) {
        $attributes = $anime['attributes'] ?? [];
        $slug = $attributes['slug'] ?? null;
        $image = $attributes['posterImage']['large'] ?? null;

        // Bind parameters and execute the statement
        try {
            $stmt->execute([
                ':anime_id' => $anime['id'] ?? null,
                ':slug' => $slug,
                ':image' => $image,
                ':title' => $attributes['canonicalTitle'] ?? null,
                ':synopsis' => $attributes['synopsis'] ?? null,
                ':ageRating' => $attributes['ageRating'] ?? null,
                ':createdAt' => $attributes['createdAt'] ?? null,
                ':endDate' => $attributes['endDate'] ?? null,
                ':episodeCount' => $attributes['episodeCount'] ?? null,
                ':popularityRank' => $attributes['popularityRank'] ?? null,
                ':showType' => $attributes['showType'] ?? null,
                ':status' => $attributes['status'] ?? null,
                ':kitsu_url' => $slug ? "https://kitsu.io/anime/{$slug}" : null,
            ]);
        } catch (PDOException $e) {
            // Skip this anime and keep going; the reason goes to the log, not the output
            error_log('db.php: insert failed for Kitsu anime ' . ($anime['id'] ?? '?') . ': ' . $e->getMessage());
        }
    }
    //fkn added sleep(1) to be nice to the api mother fucker stopped the load :(
    //idk if this is gonna break it
}





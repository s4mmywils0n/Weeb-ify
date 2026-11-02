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
    die("Error: .env file not found.");
}
$dsn = "pgsql:host=$host;port=$port;dbname=$database";

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}

$ch = "https://kitsu.io/api/edge/anime";
$page = 20;
$fetch = 800;

$sql = "CREATE TABLE IF NOT EXISTS anime (
    id SERIAL PRIMARY KEY,
    anime_id VARCHAR(255) UNIQUE,
    slug VARCHAR(255),
    image TEXT,
    title TEXT,
    synopsis TEXT,
    ageRating VARCHAR(50),
    createdAt TIMESTAMP,
    endDate TIMESTAMP,
    episodeCount INT,
    popularityRank INT,
    showType VARCHAR(50),
    status VARCHAR(50),
    kitsu_url TEXT
)";

$stmt = $pdo->prepare($sql);
$stmt->execute();

//test to see if there is data in the db 

$sql = "SELECT id FROM anime WHERE id = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    echo json_encode(['message' => 'Data already exists in the database.']);
    exit;
}

$sql = "INSERT INTO anime (anime_id, slug, image, title, synopsis, ageRating, createdAt, endDate, episodeCount, popularityRank, showType, status, kitsu_url)
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
        http_response_code(502);
        echo json_encode(['error' => 'cUrl Failed', 'detail' => $curlErr]);
        exit;
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
            echo "Error inserting anime: " . htmlspecialchars($e->getMessage()) . "\n";
        }
    }
    //fkn added sleep(1) to be nice to the api mother fucker stopped the load :(
    //idk if this is gonna break it
}





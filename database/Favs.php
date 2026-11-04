<?php

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
    echo ("Error: .env file not found.");
}
$dsn = "pgsql:host=$host;port=$port;dbname=$database";

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
} catch (PDOException $e) {
    echo ("Error connecting to the database: " . $e->getMessage());
}

$sql = "CREATE TABLE IF NOT EXISTS favorites (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    anime_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, anime_id),
    FOREIGN KEY (anime_id) REFERENCES anime(anime_id) ON DELETE CASCADE
)";

$stmt = $pdo->prepare($sql);
$stmt->execute();

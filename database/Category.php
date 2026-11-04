<?php

declare(strict_types=1);
 namespace App\Handler;
use PDO;
use PDOException;
use App\Services\PDOService;

//KILLL MEEEEEEEE FKKKKKKKKKKKN AASGHAGHGJKHSHAJSDJLKSDNJKDSJKSD 
//THE CATEGORY IS SO MUCH SHIT TO ADD BROOOOOOOO OMGGGGGGG

header("content-type: application/json; charset=UTF-8");

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

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id SERIAL PRIMARY KEY,
            kitsu_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100)
        )
    ");
    $report['steps'][] = ['create_categories', 'ok'];

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS anime_categories (
            anime_id VARCHAR(255) NOT NULL,
            category_id INT NOT NULL,
            PRIMARY KEY (anime_id, category_id),
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        )
    ");
}catch (PDOException $e) {
    $report['steps'][] = ['create_anime_categories', 'error', $e->getMessage()];
    die("Error creating tables: " . $e->getMessage());
}
// Fetch genres from Kitsu and insert into categories
$report['steps'][] = ['fetch_genres', 'starting'];

$genreUrl = 'https://kitsu.io/api/edge/genres';
$limit = 20;
$offset = 0;
$totalGenres = 0;

$upsertCat = $pdo->prepare("
    INSERT INTO categories (kitsu_id, name, slug)
    VALUES (:kitsu_id, :name, :slug)
    ON CONFLICT (kitsu_id) DO UPDATE SET
        name = EXCLUDED.name,
        slug = EXCLUDED.slug
");

while (true) {
    $url = $genreUrl . '?page[limit]=' . $limit . '&page[offset]=' . $offset;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json',
        ],
        CURLOPT_USERAGENT      => 'WeebifyGenreSync/1.0',
        CURLOPT_TIMEOUT        => 20,
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $httpCode !== 200) {
        $report['steps'][] = ['fetch_genres', 'error', $curlErr ?: "HTTP $httpCode"];
        break;
    }

    $payload = json_decode($body, true);
    $data = $payload['data'] ?? [];

    if (empty($data)) {
        break;
    }

    foreach ($data as $genre) {
        $attributes = $genre['attributes'] ?? [];
        $kitsuId = $genre['id'] ?? null;
        $name = $attributes['name'] ?? null;
        $slug = $attributes['slug'] ?? null;

        if ($kitsuId && $name) {
            $upsertCat->execute([
                ':kitsu_id' => $kitsuId,
                ':name'     => $name,
                ':slug'     => $slug,
            ]);
            $totalGenres++;
        }
    }

    // Check for next page
    $links = $payload['links'] ?? [];
    if (!empty($links['next'])) {
        $offset += $limit;
        // Be polite to the API (Kitsu allows ~60 req/min)
        usleep(500000); // 0.5 seconds
    } else {
        break;
    }
}

$report['steps'][] = ['fetch_genres', 'ok', ['inserted' => $totalGenres]];

// Verify
$finalCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$report['categories_total'] = $finalCount;

echo json_encode($report, JSON_PRETTY_PRINT);

//i was lazy for this one icl :( sorry)
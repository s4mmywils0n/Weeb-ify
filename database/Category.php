<?php
// update_genres.php
header("Content-Type: application/json; charset=UTF-8");

// 1. Database connection
$env = parse_ini_file(__DIR__ . '/../.env');
$dsn = "pgsql:host={$env['host']};port={$env['port']};dbname={$env['database']}";
$pdo = new PDO($dsn, $env['user'], $env['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// 2. Define the genres you want to keep (matches your canonical list)
$canonicalGenres = [
    'Action', 'Adventure', 'Comedy', 'Drama', 'Fantasy', 'Horror',
    'Mystery', 'Romance', 'Sci-Fi', 'Slice of Life', 'Sports',
    'Supernatural', 'Thriller', 'Mecha', 'Music', 'Psychological',
    'School', 'Shounen', 'Seinen', 'Isekai'
];

// 3. Prepare the update statement
$updateStmt = $pdo->prepare("UPDATE anime SET genres = :genres WHERE anime_id = :anime_id");

// 4. Loop through your existing anime in pages and fetch category data from Kitsu
$limit = 20;
$offset = 0;
$totalUpdated = 0;

while (true) {
    // Fetch anime IDs from your database that still need genres (or all, if re-running)
    $stmt = $pdo->prepare("SELECT anime_id FROM anime WHERE genres IS NULL OR genres = '{}' ORDER BY id LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        break; // No more anime to process
    }

    $animeIds = array_column($rows, 'anime_id');
    $idsParam = implode(',', array_map('intval', $animeIds)); // Kitsu IDs are numeric strings

    // Fetch category data for this batch from Kitsu
    $url = "https://kitsu.io/api/edge/anime?filter[id]={$idsParam}&include=categories&page[limit]={$limit}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'WeebifyGenreUpdater/1.0',
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode !== 200) {
        fwrite(STDERR, "Kitsu fetch failed for batch at offset {$offset} (HTTP {$httpCode})\n");
        $offset += $limit;
        continue;
    }

    $response = json_decode($body, true);
    $data = $response['data'] ?? [];
    $included = $response['included'] ?? [];

    // Build a map: anime_id => array of category names
    $categoryMap = []; // kitsu_category_id => name
    foreach ($included as $item) {
        if (($item['type'] ?? '') === 'categories') {
            $categoryMap[$item['id']] = $item['attributes']['title'] ?? null;
        }
    }

    $genresByAnime = [];
    foreach ($data as $anime) {
        $animeId = $anime['id'];
        $matched = [];
        foreach ($anime['relationships']['categories']['data'] ?? [] as $rel) {
            $catId = $rel['id'] ?? null;
            $name = $categoryMap[$catId] ?? null;
            if ($name && in_array($name, $canonicalGenres, true)) {
                $matched[] = $name;
            }
        }
        $genresByAnime[$animeId] = $matched;
    }

    // Update each anime with its genre array
    foreach ($genresByAnime as $animeId => $genres) {
        $pgArray = '{' . implode(',', $genres) . '}'; // e.g. {Action,Adventure}
        $updateStmt->execute([
            ':genres' => $pgArray,
            ':anime_id' => $animeId,
        ]);
        $totalUpdated++;
    }

    $offset += $limit;
    fwrite(STDERR, "Processed batch. Total updated: {$totalUpdated}\n");
    sleep(1); // Be polite to Kitsu
}

echo json_encode(['message' => 'Genre update complete', 'total_updated' => $totalUpdated]);
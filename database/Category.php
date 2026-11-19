<?php
// update_genres.php
header("Content-Type: application/json; charset=UTF-8");

set_time_limit(600);

// 1. Database connection — with SSL and pooler-friendly options
$envPath = __DIR__ . '/../.env';
$env = is_file($envPath)
    ? (parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [])
    : [];

if (empty($env['host']) || empty($env['database']) || empty($env['user'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration is missing in .env']);
    exit;
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
    $env['host'],
    $env['port'] ?? '5432',
    $env['database']
);

try {
    $pdo = new PDO($dsn, $env['user'], $env['password'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::ATTR_TIMEOUT            => 15,
    ]);
} catch (PDOException $e) {
    // The exception message can contain the host and username, so it is logged, not shown
    error_log('Category.php: database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit(1);
}

// 2. Canonical genre list
$canonicalGenres = [
    'Action', 'Adventure', 'Comedy', 'Drama', 'Fantasy', 'Horror',
    'Mystery', 'Romance', 'Sci-Fi', 'Slice of Life', 'Sports',
    'Supernatural', 'Thriller', 'Mecha', 'Music', 'Psychological',
    'School', 'Shounen', 'Seinen', 'Isekai',
];
$stmt = $pdo->prepare("SELECT 1 FROM anime WHERE id = 1 AND genres IS NOT NULL AND genres <> '{}'");

$stmt->execute();

if($stmt->rowCount() <= 0)
    {
// 3. Prepare the update statement
$updateStmt = $pdo->prepare(
    "UPDATE anime SET genres = CAST(:genres AS text[]) WHERE anime_id = :anime_id"
);

// 4. Loop through unprocessed anime
$limit        = 20;
$totalUpdated = 0;
$totalSkipped = 0;
$maxLoops     = 100;   // safety valve: 100 * 20 = 2000 rows max
$loopCount    = 0;

while ($loopCount++ < $maxLoops) {
    // No offset: the WHERE filter self-paginates. Each iteration
    // starts from the first still-unprocessed row. 
    $stmt = $pdo->prepare(
        "SELECT anime_id FROM anime
         WHERE genres IS NULL OR genres = '{}'
         ORDER BY id
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        break;
    }

    $animeIds = array_column($rows, 'anime_id');
    // Kitsu filter[id] wants the raw Kitsu IDs, not intval'd.
    // They're numeric strings, so keep them as strings.
    $idsParam = implode(',', array_map('strval', $animeIds));

    $url = 'https://kitsu.io/api/edge/anime?filter[id]=' . rawurlencode($idsParam)
         . '&include=categories&page[limit]=' . $limit;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'WeebifyGenreUpdater/1.0',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch); 

    if ($body === false || $httpCode !== 200) {
        error_log("Kitsu fetch failed (HTTP {$httpCode}): {$curlErr}");
        $totalSkipped += count($animeIds);
        sleep(2);
        continue;
    }

    $response = json_decode($body, true);
    $data     = $response['data']     ?? [];
    $included = $response['included'] ?? [];

    // Kitsu category ID -> name
    $categoryMap = [];
    foreach ($included as $item) {
        if (($item['type'] ?? '') === 'categories') {
            $categoryMap[$item['id']] = $item['attributes']['title'] ?? null;
        }
    }

    // Kitsu anime ID -> [genre names]
    $genresByAnime = [];
    foreach ($data as $anime) {
        $animeId = $anime['id'] ?? null;
        if ($animeId === null) continue;

        $matched = [];
        foreach ($anime['relationships']['categories']['data'] ?? [] as $rel) {
            $catId = $rel['id'] ?? null;
            $name  = $categoryMap[$catId] ?? null;
            if ($name !== null && in_array($name, $canonicalGenres, true)) {
                $matched[] = $name;
            }
        }
        $genresByAnime[$animeId] = $matched;
    }

    if (empty($genresByAnime)) {
        error_log("Kitsu returned no matching anime for batch");
        $totalSkipped += count($animeIds);
        sleep(1);
        continue;
    }

    // 5. Update each anime with its genre array
    foreach ($genresByAnime as $animeId => $genres) {
        // Build a properly quoted Postgres array literal.
        // Each element is double-quoted with internal quotes escaped,
        // so genre names containing commas, spaces, or quotes are safe.
        $elements = array_map(
            fn($g) => '"' . str_replace('"', '""', $g) . '"',
            $genres
        );
        $pgArray = '{' . implode(',', $elements) . '}';

        try {
            $updateStmt->execute([
                ':genres'   => $pgArray,
                ':anime_id' => $animeId,
            ]);
            $totalUpdated++;
        } catch (PDOException $e) {
            error_log("UPDATE failed for anime {$animeId}: " . $e->getMessage());
            $totalSkipped++;
        }
    }

    error_log("Processed batch. Updated: {$totalUpdated}, skipped: {$totalSkipped}");
    sleep(1);
}
    } else {
    $updateStmt = $pdo->prepare(
    "UPDATE manga SET genres = CAST(:genres AS text[]) WHERE manga_id = :manga_id"
);

// 4. Loop through unprocessed manga
$limit        = 20;
$totalUpdated = 0;
$totalSkipped = 0;
$maxLoops     = 100;
$loopCount    = 0;

while ($loopCount++ < $maxLoops) {
    // No offset: the WHERE filter self-paginates.
    $stmt = $pdo->prepare(
        "SELECT manga_id FROM manga
         WHERE genres IS NULL OR genres = '{}'
         ORDER BY id
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        break;
    }

    $mangaIds = array_column($rows, 'manga_id');
    $idsParam = implode(',', array_map('strval', $mangaIds));

    $url = 'https://kitsu.io/api/edge/manga?filter[id]=' . rawurlencode($idsParam)
         . '&include=categories&page[limit]=' . $limit;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'WeebifyMangaGenreUpdater/1.0',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    if ($body === false || $httpCode !== 200) {
        error_log("Kitsu fetch failed (HTTP {$httpCode}): {$curlErr}");
        $totalSkipped += count($mangaIds);
        sleep(2);
        continue;
    }

    $response = json_decode($body, true);
    $data     = $response['data']     ?? [];
    $included = $response['included'] ?? [];

    // Kitsu category ID -> name
    $categoryMap = [];
    foreach ($included as $item) {
        if (($item['type'] ?? '') === 'categories') {
            $categoryMap[$item['id']] = $item['attributes']['title'] ?? null;
        }
    }

    // Kitsu manga ID -> [genre names]
    $genresByManga = [];
    foreach ($data as $manga) {
        $mangaId = $manga['id'] ?? null;
        if ($mangaId === null) continue;

        $matched = [];
        foreach ($manga['relationships']['categories']['data'] ?? [] as $rel) {
            $catId = $rel['id'] ?? null;
            $name  = $categoryMap[$catId] ?? null;
            if ($name !== null && in_array($name, $canonicalGenres, true)) {
                $matched[] = $name;
            }
        }
        $genresByManga[$mangaId] = $matched;
    }

    if (empty($genresByManga)) {
        error_log("Kitsu returned no matching manga for batch");
        $totalSkipped += count($mangaIds);
        sleep(1);
        continue;
    }

    // 5. Update each manga with its genre array
    foreach ($genresByManga as $mangaId => $genres) {
        $elements = array_map(
            fn($g) => '"' . str_replace('"', '""', $g) . '"',
            $genres
        );
        $pgArray = '{' . implode(',', $elements) . '}';

        try {
            $updateStmt->execute([
                ':genres'   => $pgArray,
                ':manga_id' => $mangaId,
            ]);
            $totalUpdated++;
        } catch (PDOException $e) {
            error_log("UPDATE failed for manga {$mangaId}: " . $e->getMessage());
            $totalSkipped++;
        }
    }

    error_log("Processed batch. Updated: {$totalUpdated}, skipped: {$totalSkipped}");
    sleep(1);
}
    
    }
  


echo json_encode([
    'message'       => 'Genre update complete',
    'total_updated' => $totalUpdated,
    'total_skipped' => $totalSkipped,
]);
<?php
// update_genres_supabase.php
header("Content-Type: application/json; charset=UTF-8");


// 1. Load Supabase configuration
$envPath = dirname(__DIR__) . '/.env';
$env = is_file($envPath)
    ? (parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [])
    : [];

$supabaseUrl = rtrim((string) ($env['VITE_SUPABASE_URL'] ?? ''), '/');
$supabaseKey = (string) ($env['VITE_SUPABASE_PUBLISHABLE_KEY'] ?? '');

if ($supabaseUrl === '' || $supabaseKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Supabase API configuration is missing']);
    exit;
}

// 2. Supabase HTTP helper
function supabaseRequest(
    string $url,
    string $apiKey,
    string $method,
    ?array $payload = null,
    string $prefer = 'return=representation'
): array {
    $headers = [
        'apikey: ' . $apiKey,
        'Accept: application/json',
        'Prefer: ' . $prefer,
    ];

    $requestOptions = [
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'timeout'       => 30,
        'ignore_errors' => true,
    ];

    if ($payload !== null) {
        $requestOptions['header'] .= "\r\nContent-Type: application/json";
        $requestOptions['content'] = json_encode($payload);
    }

    $context = stream_context_create([
        'http' => $requestOptions,
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $responseText    = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;

    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
    }

    if ($responseText === false && $status === 0) {
        return ['status' => 502, 'body' => ['error' => 'Could not reach Supabase']];
    }

    $decoded = $responseText === false || $responseText === ''
        ? null
        : json_decode($responseText, true);

    return [
        'status' => $status ?: 502,
        'body'   => $decoded ?? ($responseText === '' ? null : ['error' => 'Supabase returned an invalid response']),
    ];
}

//postgres Array makes it easier to handle multi genres in the filter and anime index
// 3. Canonical genre list
$canonicalGenres = [
    'Action', 'Adventure', 'Comedy', 'Drama', 'Fantasy', 'Horror',
    'Mystery', 'Romance', 'Sci-Fi', 'Slice of Life', 'Sports',
    'Supernatural', 'Thriller', 'Mecha', 'Music', 'Psychological',
    'School', 'Shounen', 'Seinen', 'Isekai',
];

$baseUrl = $supabaseUrl . '/rest/v1/anime';

$limit  = 20;
$offset = 0;
$totalUpdated = 0;

// 4. Loop until no more rows need genres
while (true) {
    $query = http_build_query([
        'select' => 'anime_id',
        'or'     => '(genres.is.null,genres.eq.{})',
        'order'  => 'id.asc',
        'limit'  => $limit,
        'offset' => $offset,
    ], '', '&', PHP_QUERY_RFC3986);

    $result = supabaseRequest($baseUrl . '?' . $query, $supabaseKey, 'GET');

    if ($result['status'] < 200 || $result['status'] >= 300) {
        error_log("Supabase SELECT failed (HTTP {$result['status']}): "
            . json_encode($result['body']) . "\n");
        $offset += $limit;
        continue;
    }

    $rows = is_array($result['body']) ? $result['body'] : [];
    if (empty($rows)) {
        break;
    }

    $animeIds = array_column($rows, 'anime_id');
    $idsParam = implode(',', array_map('intval', $animeIds));

    $kitsuUrl = "https://kitsu.io/api/edge/anime"
              . "?filter[id]={$idsParam}&include=categories&page[limit]={$limit}";

    $ch = curl_init($kitsuUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'WeebifyGenreUpdater/1.0',
        CURLOPT_TIMEOUT        => 30,
    ]);
    $kitsuBody = curl_exec($ch);
    $kitsuCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($kitsuBody === false || $kitsuCode !== 200) {
        error_log("Kitsu fetch failed at offset {$offset} (HTTP {$kitsuCode})\n");
        $offset += $limit;
        sleep(1);
        continue;
    }

    $kitsuData     = json_decode($kitsuBody, true);
    $animeList     = $kitsuData['data']     ?? [];
    $included      = $kitsuData['included'] ?? [];

    $categoryMap = [];
    foreach ($included as $item) {
        if (($item['type'] ?? '') === 'categories') {
            $categoryMap[$item['id']] = $item['attributes']['title'] ?? null;
        }
    }

    $genresByAnime = [];
    foreach ($animeList as $anime) {
        $animeId = $anime['id'];
        $matched = [];
        foreach ($anime['relationships']['categories']['data'] ?? [] as $rel) {
            $catId = $rel['id'] ?? null;
            $name  = $categoryMap[$catId] ?? null;
            if ($name && in_array($name, $canonicalGenres, true)) {
                $matched[] = $name;
            }
        }
        $genresByAnime[$animeId] = $matched;
    }

    foreach ($genresByAnime as $animeId => $genres) {
        $patchQuery = http_build_query(
            ['anime_id' => 'eq.' . $animeId],
            '', '&', PHP_QUERY_RFC3986
        );

        $patch = supabaseRequest(
            $baseUrl . '?' . $patchQuery,
            $supabaseKey,
            'PATCH',
            ['genres' => $genres],
            'return=minimal'
        );

        if ($patch['status'] < 200 || $patch['status'] >= 300) {
            error_log("PATCH anime {$animeId} failed (HTTP {$patch['status']}): "
                . json_encode($patch['body']) . "\n");
            continue;
        }

        $totalUpdated++;
    }

    $offset += $limit;
    error_log("Processed batch. Total updated: {$totalUpdated}\n");
   //doesnt deserve to sleep
}

echo json_encode([
    'message'       => 'Genre update complete',
    'total_updated' => $totalUpdated,
]);
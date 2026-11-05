<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

$resources = [
    'anime' => [
        'anime_id', 'slug', 'image', 'title', 'synopsis', 'agerating',
        'createdat', 'enddate', 'episodecount', 'popularityrank',
        'showtype', 'status', 'kitsu_url',
    ],
    'manga' => [
        'title', 'author', 'year_published', 'number_of_volumes',
        'number_of_chapters', 'genre', 'status',
    ],
];

function sendJson(int $status, mixed $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Send one request to Supabase's REST API. The publishable key is sent only in
 * the apikey header; Supabase RLS still determines which rows are accessible.
 *
 * @return array{status:int, body:mixed}
 */
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
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'timeout' => 12,
        'ignore_errors' => true,
    ];

    if ($payload !== null) {
        $requestOptions['header'] .= "\r\nContent-Type: application/json";
        $requestOptions['content'] = json_encode($payload);
    }

    $context = stream_context_create([
        'http' => $requestOptions,
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $responseText = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;

    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches)) {
        $status = (int) $matches[1];
    }

    if ($responseText === false && $status === 0) {
        return [
            'status' => 502,
            'body' => ['error' => 'Could not reach Supabase'],
        ];
    }

    $decoded = $responseText === false || $responseText === ''
        ? null
        : json_decode($responseText, true);

    return [
        'status' => $status ?: 502,
        'body' => $decoded ?? ($responseText === '' ? null : ['error' => 'Supabase returned an invalid response']),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$apiPosition = strpos($uri, '/api.php');

if ($apiPosition === false) {
    sendJson(404, ['error' => 'Endpoint not found']);
}

$path = substr($uri, $apiPosition + strlen('/api.php'));

if (!preg_match('#^/(anime|manga)(?:/([0-9]+))?/?$#', $path, $matches)) {
    sendJson(404, ['error' => 'Endpoint not found']);
}

$table = $matches[1];
$recordId = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : null;
$allowedFields = $resources[$table];
$baseUrl = $supabaseUrl . '/rest/v1/' . rawurlencode($table);

if ($recordId === null && $method === 'GET') {
    $page = filter_var(
        $_GET['page'] ?? '1',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 1000000]]
    );

    if ($page === false) {
        sendJson(400, ['error' => 'The page parameter must be an integer between 1 and 1000000']);
    }

    $itemsPerPage = 20;
    $query = http_build_query([
        'select' => '*',
        'order' => 'id.asc',
        'limit' => $itemsPerPage,
        'offset' => ($page - 1) * $itemsPerPage,
    ], '', '&', PHP_QUERY_RFC3986);

    $result = supabaseRequest($baseUrl . '?' . $query, $supabaseKey, 'GET');
    sendJson($result['status'], $result['body']);
}

if ($recordId !== null && $method === 'GET') {
    $query = http_build_query([
        'select' => '*',
        'id' => 'eq.' . $recordId,
        'limit' => 1,
    ], '', '&', PHP_QUERY_RFC3986);

    $result = supabaseRequest($baseUrl . '?' . $query, $supabaseKey, 'GET');
    if ($result['status'] < 200 || $result['status'] >= 300) {
        sendJson($result['status'], $result['body']);
    }

    $records = is_array($result['body']) ? $result['body'] : [];
    if (empty($records)) {
        sendJson(404, ['error' => 'Record not found']);
    }
    sendJson(200, $records[0]);
}

if ($recordId === null && $method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || array_is_list($input)) {
        sendJson(400, ['error' => 'Invalid JSON object']);
    }

    $unknownFields = array_diff(array_keys($input), $allowedFields);
    if ($unknownFields) {
        sendJson(400, ['error' => 'Unknown field provided']);
    }

    if (!isset($input['title']) || !is_string($input['title']) || trim($input['title']) === '') {
        sendJson(400, ['error' => 'A non-empty title is required']);
    }

    $result = supabaseRequest($baseUrl, $supabaseKey, 'POST', $input);
    if ($result['status'] < 200 || $result['status'] >= 300) {
        sendJson($result['status'], $result['body']);
    }

    $records = is_array($result['body']) ? $result['body'] : [];
    sendJson(201, $records[0] ?? $result['body']);
}

if ($recordId !== null && in_array($method, ['PUT', 'PATCH'], true)) {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || array_is_list($input)) {
        sendJson(400, ['error' => 'Invalid JSON object']);
    }

    $unknownFields = array_diff(array_keys($input), $allowedFields);
    if ($unknownFields) {
        sendJson(400, ['error' => 'Unknown field provided']);
    }

    if (array_key_exists('title', $input) && (!is_string($input['title']) || trim($input['title']) === '')) {
        sendJson(400, ['error' => 'Title cannot be empty']);
    }

    if ($method === 'PUT') {
        if (!isset($input['title']) || !is_string($input['title']) || trim($input['title']) === '') {
            sendJson(400, ['error' => 'A non-empty title is required']);
        }
        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $input)) {
                $input[$field] = null;
            }
        }
    }

    if (!$input) {
        sendJson(400, ['error' => 'No fields provided']);
    }

    $query = http_build_query(['id' => 'eq.' . $recordId], '', '&', PHP_QUERY_RFC3986);
    $result = supabaseRequest($baseUrl . '?' . $query, $supabaseKey, 'PATCH', $input);
    if ($result['status'] < 200 || $result['status'] >= 300) {
        sendJson($result['status'], $result['body']);
    }

    $records = is_array($result['body']) ? $result['body'] : [];
    if (empty($records)) {
        sendJson(404, ['error' => 'Record not found']);
    }
    sendJson(200, $records[0]);
}

if ($recordId !== null && $method === 'DELETE') {
    $query = http_build_query(['id' => 'eq.' . $recordId], '', '&', PHP_QUERY_RFC3986);
    $result = supabaseRequest(
        $baseUrl . '?' . $query,
        $supabaseKey,
        'DELETE',
        null,
        'return=representation'
    );
    if ($result['status'] < 200 || $result['status'] >= 300) {
        sendJson($result['status'], $result['body']);
    }

    if (empty($result['body'])) {
        sendJson(404, ['error' => 'Record not found']);
    }
    sendJson(200, ['message' => 'Record deleted successfully']);
}

http_response_code(405);
header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
echo json_encode(['error' => 'Method not allowed']);

<?php
declare(strict_types=1);

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$publicBasePath = rtrim(dirname($scriptName), '/');
if ($publicBasePath === '.') {
    $publicBasePath = '';
}
$apiBaseUrl = $publicBasePath . '/api.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($publicBasePath !== '' && str_starts_with($requestPath, $publicBasePath)) {
    $requestPath = substr($requestPath, strlen($publicBasePath));
}
$requestPath = '/' . trim($requestPath, '/');
if ($requestPath === '//') {
    $requestPath = '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function renderView(string $name): void
{
    global $apiBaseUrl, $publicBasePath;
    $viewApiBaseUrl = $apiBaseUrl;
    $viewRouteBase = $publicBasePath;
    require dirname(__DIR__) . '/app/views/' . $name . '.php';
}

$routes = [
    'GET /' => 'home',
    'GET /home' => 'home',
    'GET /anime' => 'anime',
    'GET /anime-id' => 'anime-id',
    'GET /manga' => 'manga',
    'GET /products' => 'products',
];

$viewName = $routes[$method . ' ' . $requestPath] ?? null;
if ($viewName === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Page not found';
    exit;
}

renderView($viewName);

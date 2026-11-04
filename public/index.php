<?php
declare(strict_types=1);

session_start();

use app/src/BrowseController;
use app/src/FavsController;
use app/src/LoadController;

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $parts = explode('\\', substr($class, strlen($prefix)));
    $file = array_pop($parts);
    $dir = $parts ? strtolower(implode('/', $parts)) . '/' : ''; 

    $path = APP_ROOT . '/app/' . $dir . $file . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require APP_ROOT . '/database/db.php';

function jsonResponse(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
}

function view(string $name): void
{
    require APP_ROOT . '/app/views/' . $name . '.php';
}

$routes = [
    'GET /' => fn() => header('Location: ' . (isset($_SESSION['customer_id']) ? '/dashboard' : '/login')),

    'GET /anime' => fn() => view('anime'),
    'GET /manga' => fn() => view('manga'),
    'GET /home' => fn() => view('home'),

    'POST /register' => [AuthController::class, 'register'],
    'POST /login' => [AuthController::class, 'login'],
    'GET /logout' => [AuthController::class, 'logout'],

    'GET /thresholds' => [ThresholdController::class, 'index'],
    'POST /thresholds' => [ThresholdController::class, 'update'],
    'GET /api/thresholds' => [ThresholdController::class, 'json'],

    'POST /api/readings' => [ReadingApiController::class, 'store'],
    'GET /api/readings/latest' => [ReadingApiController::class, 'latest'],

    'GET /api/fan'     => [FanApiController::class, 'show'],
    'POST /api/fan'    => [FanApiController::class, 'update'],
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$handler = $routes["$method $path"] ?? null;

if ($handler === null) {
    jsonResponse(404, ['status' => 'error', 'message' => 'Endpoint not found.']);
    exit;
}

if ($handler instanceof Closure) {
    $handler();
} else {
    [$class, $action] = $handler;
    (new $class())->$action();
}
<?php

declare(strict_types=1);

require_once __DIR__ . '/routing.php';

$router = new Router();

// 1. Closure / Anonymous Function Route
$router->get('/', function () {
    echo 'Welcome to the Homepage!';
});

// 2. Dynamic Parameter Route
$router->get('/users/{id}', function (string $id) {
    echo "Viewing profile for user ID: " . htmlspecialchars($id);
});

// 3. Controller Method Route (requires UserController to be loaded)
// $router->post('/users/create', [UserController::class, 'store']);

// Dispatch the current request
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
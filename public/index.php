<?php

declare(strict_types=1);


require_once __DIR__ . '/../app/core/Router.php';
require_once __DIR__ . '/../app/Services/PDOService.php';
require_once __DIR__ . '/../app/models/AccountModel.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/middleware/Middleware.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/middleware/GuestMiddleware.php';
require_once __DIR__ . '/../app/middleware/OwnsProfileMiddleware.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';

use App\Controllers\AuthController;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\OwnsProfileMiddleware;

// Sessions remember who is logged in between requests
if (session_status() === PHP_SESSION_NONE) {
   session_start();
}


// ---------------------------------------------------------------------------
// 1. Work out the base URL
// ---------------------------------------------------------------------------
// The site can be reached two ways:
//   http://localhost/Weeb-ify/anime          (root .htaccess forwards into public/)
//   http://localhost/Weeb-ify/public/anime   (hitting public/ directly)
// SCRIPT_NAME is always /Weeb-ify/public/index.php, so we check which prefix
// the requested URL actually starts with and strip that one.
$uri        = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$scriptDir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // /Weeb-ify/public
$projectDir = preg_replace('#/public$#', '', $scriptDir);                           // /Weeb-ify

$startsWith = fn(string $prefix): bool =>
    $prefix !== '' && ($uri === $prefix || str_starts_with($uri, $prefix . '/'));

if ($startsWith($scriptDir)) {
    $basePath = $scriptDir;
} elseif ($startsWith($projectDir)) {
    $basePath = $projectDir;
} else {
    $basePath = '';
}

// Used by the views to build links: BASE_URL . '/anime'
define('BASE_URL', $basePath);
// api.php lives in the project root (outside public/)
define('API_URL', $projectDir . '/api.php');

$uri = '/' . ltrim(substr($uri, strlen($basePath)), '/');

// api.php lives outside public/. Apache serves it directly, but `php -S ... -t public`
// can't see it and sends /api.php/... here instead, so hand those requests over.
if ($uri === '/api.php' || str_starts_with($uri, '/api.php/')) {
    require __DIR__ . '/../api.php';
    exit;
}

// The current page inside the site, with its query string, e.g. /profile/1/favorites?page=2
$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
define('CURRENT_PATH', $uri . ($query ? '?' . $query : ''));

// ---------------------------------------------------------------------------
// 2. Render a view from app/views, passing route params in as variables
// ---------------------------------------------------------------------------
function view(string $name, array $data = []): void
{
    extract($data); // ['id' => '5'] becomes $id inside the view
    require __DIR__ . "/../app/views/{$name}.php";
}

// ---------------------------------------------------------------------------
// Login helpers
// ---------------------------------------------------------------------------
// The id of the logged-in user, or null when nobody is logged in.
// AuthController::login() sets $_SESSION['user_id'].
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

// Is the logged-in user an admin (user.is_admin)? Read from the database once per request,
// never trusted from the session, so revoking admin works immediately.
// Used to show the "Admin" link and by AdminMiddleware; the API checks again on every write.
function currentUserIsAdmin(): bool
{
    static $cache = [];
    $userId = currentUserId();
    if ($userId === null) {
        return false;
    }
    if (!array_key_exists($userId, $cache)) {
        try {
            $cache[$userId] = (new \App\Models\AccountModel(new \App\Services\PDOService()))->isAdmin($userId);
        } catch (\Throwable $e) {
            error_log('Admin check failed: ' . $e->getMessage());
            $cache[$userId] = false; // fail closed
        }
    }
    return $cache[$userId];
}

// Send the browser to another page on this site and stop.
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

// Only allow redirects to pages on this site ("/anime"), never "//evil.com" or "https://...".
function isSafeLocalPath(string $path): bool
{
    return str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_contains($path, '\\');
}

// ---------------------------------------------------------------------------
// 3. Routes  (placeholder names must match the closure parameter names)
// ---------------------------------------------------------------------------
$router = new Router();

$router->get('/', fn() => view('home'));

$router->get('/anime', fn() => view('anime'));

$router->get('/anime/{id}', fn(string $id) => view('anime-id', ['id' => $id]));

$router->get('/manga', fn() => view('manga'));

$router->get('/manga/{id}', fn(string $id) => view('manga-id', ['id' => $id]));

$router->get('/products', fn() =>view('products'));

// Admin: edit the anime and manga catalogue (logged out -> /login, not admin -> 403).
// The page only calls the API; the API checks admin again on every write.
$router->get('/admin', fn() => view('admin'), [AuthMiddleware::class, AdminMiddleware::class]);


// Middleware (3rd argument) runs first, in order:
//   AuthMiddleware        - must be logged in (otherwise -> /login, then back here)
//   GuestMiddleware       - must be logged OUT (otherwise -> /)
//   OwnsProfileMiddleware - {user} in the URL must be you (list after AuthMiddleware)

// Header "Favorites" button: logged in -> your favorites, otherwise -> log in
$router->get('/favorites', function () {
    redirect('/profile/' . currentUserId() . '/favorites');
}, [AuthMiddleware::class]);

$router->get('/profile/{user}/favorites', function (string $user) {
    view('favorites', ['user' => $user]);
}, [AuthMiddleware::class, OwnsProfileMiddleware::class]);

$router->get('/login', fn() => view('login'), [GuestMiddleware::class]);

// Login form submits here: check the password, then go back to the page that needed login (or home)
$router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class]);

$router->get('/register', fn() => view('register'), [GuestMiddleware::class]);

// Header "Log out" button submits here: forget who is logged in, then go home
$router->post('/logout', [AuthController::class, 'logout']);

// Sign-up form submits here: validate, check username/email, create the user
$router->post('/register', [AuthController::class, 'register'], [GuestMiddleware::class]);





// ---------------------------------------------------------------------------
// 4. Dispatch
// ---------------------------------------------------------------------------
$router->dispatch($uri, $_SERVER['REQUEST_METHOD']);

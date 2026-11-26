<?php
/*
 * Shared <head> + site header. Include it at the top of every view:
 *
 *   $title  = 'Anime — Weebi:fy';
 *   $active = 'anime';
 *   require __DIR__ . '/partials/header.php';
 *
 * Optional variables (set them before the require):
 *   $title             <title> text                       default 'Weebi:fy'
 *   $active            highlighted nav item: home | anime | manga | products | favorites | admin
 *   $searchAction      where the header search submits    default BASE_URL . '/anime'
 *   $searchQuery       value to show in the search box    default ''
 *   $searchPlaceholder search box placeholder             default 'Search your collection'
 *   $extraHead         extra HTML for <head>, e.g. a page-only <style> block
 */
if (!defined('BASE_URL')) { http_response_code(404); exit; }

$uri = parse_url($_SERVER['sCRIPT_NAME'], PHP_URL_PATH);

$apiPosition = strpos($uri, '/api.php');

$path = substr(
    $uri,
    $apiPosition + strlen('/api.php')
);

$title             ??= 'Weebi:fy';
$active            ??= '';
$searchAction      ??= BASE_URL . '/anime';
$searchQuery       ??= '';
$searchPlaceholder ??= 'Search your collection';
$extraHead         ??= '';

$e = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$current = fn(string $item): string => $active === $item ? ' aria-current="page"' : '';


$loggedInUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
// Optional: fetch the user's display name once you have a model for it
$loggedInName = $loggedInUserId !== null
    ? ($_SESSION['username'] ?? 'Account')
    : null;


$catalogueSearch = in_array($searchAction, [BASE_URL . '/anime', BASE_URL . '/manga'], true);
$catalogueType = $searchAction === BASE_URL . '/manga' ? 'manga' : 'anime';
$suggestionCount = $catalogueType === 'manga' ? 10 : 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171421">
    <title><?= $e($title) ?></title>
    <?= $extraHead ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/site-chrome.css">
    <?php if ($catalogueSearch): ?>
    <script src="<?= BASE_URL ?>/assets/js/header-search.js" defer></script>
    <?php endif; ?>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="<?= BASE_URL ?>/" aria-label="Weebi:fy home">Weebi<span>:fy</span></a>

        <nav class="primary-nav" aria-label="Main navigation">
            <a href="<?= BASE_URL ?>/anime"<?= $current('anime') ?>>Anime</a>
            <a href="<?= BASE_URL ?>/manga"<?= $current('manga') ?>>Manga</a>
            <a href="<?= BASE_URL ?>/products"<?= $current('products') ?>>Products</a>
            <!-- /favorites checks login: your favorites if logged in, otherwise the log in page -->
            <a href="<?= BASE_URL ?>/favorites"<?= $current('favorites') ?>>Favorites</a>
            <?php if ($loggedInUserId !== null && currentUserIsAdmin()): ?>
            <a href="<?= BASE_URL ?>/admin"<?= $current('admin') ?>>Admin</a>
            <?php endif; ?>
        </nav>

        <form class="header-search" role="search" action="<?= $e($searchAction) ?>" method="get"<?php if ($catalogueSearch): ?>
              data-catalogue="<?= $e($catalogueType) ?>"
              data-suggestion-count="<?= $suggestionCount ?>"
              data-suggest-url="<?= $e(API_URL . '/' . $catalogueType) ?>"
              data-results-url="<?= $e(BASE_URL . '/' . $catalogueType) ?>"
              data-detail-url="<?= $e(BASE_URL . '/' . $catalogueType) ?>"<?php endif; ?>>
            <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <input type="search" name="q" value="<?= $e($searchQuery) ?>" placeholder="<?= $e($searchPlaceholder) ?>" aria-label="<?= $e($searchPlaceholder) ?>">
        </form>

        <?php if ($loggedInUserId === null): ?>
    <a class="login-link" href="<?= BASE_URL ?>/login">Log in</a>
<?php else: ?>
    <div class="account-menu">
        <span class="eyebrow"><?= $e($loggedInName) ?></span>
        <form method="post" action="<?= BASE_URL ?>/logout">
            <button type="submit" class="back-link">Log out</button>
        </form>
    </div>
<?php endif; ?>
    </header>

<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

$title = 'Access denied — Weebi:fy';
$active = '';
require __DIR__ . '/partials/header.php';
?>

<main>
    <a class="back-link" href="<?= BASE_URL ?>/">← Back to home</a>
    <span class="eyebrow">403</span>
    <h1>Admins <span>only.</span></h1>
    <p class="status-message">Your account doesn't have access to this page.</p>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

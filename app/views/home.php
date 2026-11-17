<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

$title = 'Weebi:fy — Find your favorites';
$active = 'home';
require __DIR__ . '/partials/header.php';
?>

    <main>
        <section class="welcome" aria-labelledby="welcome-title">
            <span class="eyebrow">Your collection, your way</span>
            <h1 id="welcome-title">Find your <span>favorites.</span></h1>
            <p>Keep track of the anime, manga, and collectibles that make your shelves feel like yours.</p>
            <a class="browse-link" href="<?= BASE_URL ?>/anime">Explore anime <span aria-hidden="true">→</span></a>
        </section>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>

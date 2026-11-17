<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

// $user comes from the route /profile/{user}/favorites (see public/index.php).
// For now it is the numeric user id. TODO: once login exists, check it matches the logged-in user.
$userId = filter_var($user ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false || $userId === null) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$envPath = dirname(__DIR__, 2) . '/.env';
$env = is_file($envPath)
    ? (parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [])
    : [];

$supabaseUrl = rtrim((string) ($env['url'] ?? ''), '/');
$supabaseKey = (string) ($env['key'] ?? '');

$favorites = [];
$error = '';

if ($supabaseUrl === '' || $supabaseKey === '') {
    $error = 'Supabase API configuration is missing.';
} else {
    $query = http_build_query([
        'select'  => '*,anime:anime_id(id,anime_id,title,image,episodecount,status,showtype,kitsu_url,agerating,popularityrank)',
        'user_id' => 'eq.' . $userId,
        'order'   => 'created_at.desc',
    ], '', '&', PHP_QUERY_RFC3986);

    $headers = [
        'apikey: ' . $supabaseKey,
        'Accept: application/json',
    ];

    $context = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => implode("\r\n", $headers),
            'timeout'       => 12,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $body = @file_get_contents($supabaseUrl . '/rest/v1/favorites?' . $query, false, $context);
    $status = 0;
    $responseHeaders = $http_response_header ?? [];
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
    }

    if ($body === false) {
        $error = 'Could not reach Supabase.';
    } elseif ($status < 200 || $status >= 300) {
        $decoded = json_decode($body, true);
        $error = $decoded['message'] ?? 'Could not load favorites.';
    } else {
        $decoded = json_decode($body, true);
        $favorites = is_array($decoded) ? $decoded : [];
    }
}

function safeImageUrl($value): string
{
    return is_string($value) && preg_match('#^https?://#i', trim($value))
        ? trim($value)
        : '';
}
?>
<?php ob_start(); ?>
    
<?php $extraHead = ob_get_clean(); ?>
<?php
$title = 'Favorites — Weebi:fy';
$active = 'favorites';
$searchPlaceholder = 'Search favorites';
require __DIR__ . '/partials/header.php';
?>

    <main>
        <a class="back-link" href="<?= BASE_URL ?>/anime">← Back to anime</a>
        <br>
        <span class="eyebrow">Your library</span>
        <h1>Favorite <span>anime.</span></h1>
        <br>
        <section id="favorites-content" aria-live="polite">
            <?php if ($error !== ''): ?>
                <p class="status-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif (count($favorites) === 0): ?>
                <p class="status-message">
                    You haven't favorited any anime yet. Browse the <a href="<?= BASE_URL ?>/anime" style="color: var(--accent);">anime collection</a> and add some.
                </p>
            <?php else: ?>
                <div class="favorites-grid">
                    <?php foreach ($favorites as $fav): ?>
                        <?php
                        $anime = $fav['anime'] ?? [];
                        $title = $anime['title'] ?? 'Untitled anime';
                        $image = safeImageUrl($anime['image'] ?? $fav['image'] ?? '');
                        $episodeCount = $anime['episodecount'] ?? $fav['episode_count'] ?? null;
                        $showType = $anime['showtype'] ?? $fav['show_type'] ?? null;
                        $status = $anime['status'] ?? null;
                        $animeId = $fav['anime_id'] ?? ($anime['anime_id'] ?? '');
                        $meta = array_filter([$showType, $episodeCount ? $episodeCount . ' episodes' : null, $status]);
                        $detailHref = BASE_URL . '/anime/' . urlencode((string)($anime['id'] ?? ''));
                        ?>
                        <article class="favorite-card" data-anime-id="<?= htmlspecialchars((string)$animeId, ENT_QUOTES, 'UTF-8') ?>">
                            <a href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>" class="favorite-poster-link" aria-label="View details for <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="favorite-poster">
                                    <?php if ($image !== ''): ?>
                                        <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> poster" loading="lazy">
                                    <?php else: ?>
                                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="favorite-body">
                                <h2>
                                    <a href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>" style="color: inherit; text-decoration: none;">
                                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h2>
                                <p><?= htmlspecialchars(implode(' · ', $meta) ?: 'No details', ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="favorite-actions">
                                    <a href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>">View</a>
                                    <button type="button" class="remove-favorite" data-anime-id="<?= htmlspecialchars((string)$animeId, ENT_QUOTES, 'UTF-8') ?>">Remove</button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <script>
        document.querySelectorAll('.remove-favorite').forEach(button => {
            button.addEventListener('click', async () => {
                const animeId = button.dataset.animeId;
                if (!animeId) return;

                const card = button.closest('.favorite-card');
                button.disabled = true;
                button.textContent = 'Removing…';

                try {
                    const res = await fetch(`<?= API_URL ?>/favorites/anime/${encodeURIComponent(animeId)}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await res.json().catch(() => null);
                    if (!res.ok) {
                        throw new Error(data?.error || `Request failed (${res.status})`);
                    }
                    if (card) {
                        card.style.transition = 'opacity .2s ease, transform .2s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(.95)';
                        setTimeout(() => {
                            card.remove();
                            const grid = document.querySelector('.favorites-grid');
                            if (grid && grid.children.length === 0) {
                                const empty = document.createElement('p');
                                empty.className = 'status-message';
                                empty.innerHTML = 'You haven\'t favorited any anime yet. Browse the <a href="<?= BASE_URL ?>/anime" style="color: var(--accent);">anime collection</a> and add some.';
                                grid.replaceWith(empty);
                            }
                        }, 200);
                    }
                } catch (err) {
                    button.disabled = false;
                    button.textContent = 'Remove';
                    alert(err.message);
                }
            });
        });
    </script>
<?php require __DIR__ . '/partials/footer.php'; ?>

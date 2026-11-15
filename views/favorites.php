<?php
$userId = 1;

$envPath = dirname(__DIR__) . '/.env';
$env = is_file($envPath)
    ? (parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [])
    : [];

$supabaseUrl = rtrim((string) ($env['VITE_SUPABASE_URL'] ?? ''), '/');
$supabaseKey = (string) ($env['VITE_SUPABASE_PUBLISHABLE_KEY'] ?? '');

$favorites = [];
$error = '';

if ($supabaseUrl === '' || $supabaseKey === '') {
    $error = 'Supabase API configuration is missing.';
} else {
    $query = http_build_query([
        'select'  => '*,anime:anime_id(anime_id,title,image,episodecount,status,showtype,kitsu_url,agerating,popularityrank)',
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171421">
    <title>Favorites — Weebi:fy</title>
    <style>
        :root {
            color-scheme: dark;
            --ink: #f7f3ff;
            --muted: #b8b0c9;
            --accent: #d6a8ff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            background: radial-gradient(ellipse at 75% 10%, #382448 0, #1a1722 42%, #121117 100%);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        .site-header {
            display: flex;
            align-items: center;
            gap: 28px;
            min-height: 78px;
            padding: 14px clamp(20px, 5vw, 72px);
            border-bottom: 1px solid #ffffff16;
            background: #171421cc;
        }

        .brand {
            flex: 0 0 auto;
            color: var(--ink);
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -.06em;
            text-decoration: none;
        }

        .brand span { color: var(--accent); }
        .primary-nav { display: flex; align-items: center; gap: 8px; }

        .primary-nav a, .login-link {
            padding: 10px 13px;
            border-radius: 999px;
            color: var(--muted);
            font-size: .94rem;
            font-weight: 650;
            text-decoration: none;
            transition: background .18s ease, color .18s ease;
        }

        .primary-nav a:hover, .primary-nav a[aria-current="page"] {
            color: var(--ink);
            background: #ffffff12;
        }

        .header-search {
            display: flex;
            align-items: center;
            gap: 8px;
            width: min(280px, 25vw);
            min-width: 170px;
            margin-left: auto;
            padding: 0 13px;
            border: 1px solid #ffffff20;
            border-radius: 999px;
            background: #ffffff0a;
        }

        .header-search svg { flex: 0 0 auto; color: var(--muted); }

        .header-search input {
            width: 100%;
            min-width: 0;
            padding: 10px 0;
            border: 0;
            outline: 0;
            color: var(--ink);
            background: transparent;
            font: inherit;
        }

        .header-search input::placeholder { color: #a59bb5; }

        .login-link {
            flex: 0 0 auto;
            color: #21152c;
            background: var(--accent);
        }

        .login-link:hover { background: #e5c9ff; }

        main { width: min(100% - 40px, 1000px); margin: 0 auto; padding: 52px 0 72px; }

        .back-link {
            display: inline-flex;
            margin-bottom: 25px;
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        .back-link:hover { text-decoration: underline; }

        .eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            color: var(--accent);
            font-size: .76rem;
            font-weight: 750;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 25px;
            font-size: clamp(2.5rem, 6vw, 4.3rem);
            line-height: .98;
            letter-spacing: -.075em;
        }

        h1 span { color: var(--accent); }

        .status-message {
            margin: 0;
            padding: 24px;
            border: 1px solid #ffffff12;
            border-radius: 16px;
            color: var(--muted);
            background: #211d2ccc;
            text-align: center;
        }

        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }

        .favorite-card {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #ffffff12;
            border-radius: 16px;
            background: #211d2ccc;
            box-shadow: 0 12px 38px #00000025;
            transition: transform .18s ease, border-color .18s ease;
        }

        .favorite-card:hover {
            transform: translateY(-3px);
            border-color: #ffffff24;
        }

        .favorite-poster {
            display: grid;
            width: 100%;
            aspect-ratio: 3 / 4;
            place-items: center;
            overflow: hidden;
            color: var(--accent);
            background: linear-gradient(145deg, #493354, #252131 70%);
            font-size: 1rem;
            font-weight: 800;
            text-align: center;
            padding: 12px;
        }

        .favorite-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .favorite-body {
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .favorite-body h2 {
            margin: 0;
            font-size: 1rem;
            line-height: 1.3;
            letter-spacing: -.02em;
        }

        .favorite-body p {
            margin: 0;
            color: var(--muted);
            font-size: .82rem;
            line-height: 1.45;
        }

        .favorite-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
            padding-top: 8px;
        }

        .favorite-actions a, .favorite-actions button {
            flex: 1;
            padding: 8px 10px;
            border: 1px solid #ffffff1a;
            border-radius: 999px;
            color: var(--ink);
            background: #ffffff0a;
            font: inherit;
            font-size: .78rem;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: background .18s ease, color .18s ease;
        }

        .favorite-actions a:hover { background: #ffffff1a; }

        .favorite-actions button {
            color: #f9c9d4;
            border-color: #ff7a9c33;
        }

        .favorite-actions button:hover {
            background: #ff7a9c1a;
            color: #ffd6e0;
        }

        .favorite-actions button[disabled] {
            opacity: .55;
            cursor: not-allowed;
        }

        @media (max-width: 760px) {
            .site-header { flex-wrap: wrap; gap: 10px 16px; }
            .brand { margin-right: auto; }
            .primary-nav { order: 3; width: 100%; justify-content: center; }
            .primary-nav a { padding: 9px 11px; }
            .header-search { order: 2; width: min(230px, 46vw); min-width: 130px; margin-left: 0; }
            .login-link { order: 2; padding: 9px 12px; }
        }

        @media (max-width: 560px) {
            .site-header { padding-inline: 14px; }
            .primary-nav { gap: 0; }
            .primary-nav a { padding-inline: 9px; font-size: .88rem; }
            .header-search { width: 100%; order: 4; }
            .login-link { margin-left: auto; }
            main { width: min(100% - 28px, 1000px); padding-top: 35px; }
            .favorites-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px; }
        }
    </style>
    <link rel="stylesheet" href="site-chrome.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="home.php" aria-label="Weebi:fy home">Weebi<span>:fy</span></a>

        <nav class="primary-nav" aria-label="Main navigation">
            <a href="anime.php">Anime</a>
            <a href="manga.php">Manga</a>
            <a href="products.php">Products</a>
            <a href="favorites.php" aria-current="page">Favorites</a>
        </nav>

        <form class="header-search" role="search" action="" method="get">
            <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <input type="search" name="q" placeholder="Search favorites" aria-label="Search favorites">
        </form>

        <a class="login-link" href="../app/src/Login">Log in</a>
    </header>

    <main>
        <a class="back-link" href="anime.php">← Back to anime</a>
        <span class="eyebrow">Your library</span>
        <h1>Favorite <span>anime.</span></h1>

        <section id="favorites-content" aria-live="polite">
            <?php if ($error !== ''): ?>
                <p class="status-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif (count($favorites) === 0): ?>
                <p class="status-message">
                    You haven't favorited any anime yet. Browse the <a href="anime.php" style="color: var(--accent);">anime collection</a> and add some.
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
                        $detailHref = 'anime-id.php?id=' . urlencode((string)($anime['id'] ?? ''));
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
    <footer class="site-footer">
        <p>Weebi:fy — Your collection, your way.</p>
        <nav class="footer-nav" aria-label="Footer navigation">
            <a href="anime.php">Anime</a>
            <a href="manga.php">Manga</a>
            <a href="products.php">Products</a>
            <a href="favorites.php">Favorites</a>
        </nav>
    </footer>

    <script>
        document.querySelectorAll('.remove-favorite').forEach(button => {
            button.addEventListener('click', async () => {
                const animeId = button.dataset.animeId;
                if (!animeId) return;

                const card = button.closest('.favorite-card');
                button.disabled = true;
                button.textContent = 'Removing…';

                try {
                    const res = await fetch(`../api.php/favorites/anime/${encodeURIComponent(animeId)}`, {
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
                                empty.innerHTML = 'You haven\'t favorited any anime yet. Browse the <a href="anime.php" style="color: var(--accent);">anime collection</a> and add some.';
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
</body>
</html>
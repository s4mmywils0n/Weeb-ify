<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171421">
    <title>Weebi:fy — Find your favorites</title>
    <style>
        :root {
            color-scheme: dark;
            --ink: #f7f3ff;
            --muted: #b8b0c9;
            --surface: #211d2c;
            --accent: #d6a8ff;
            --accent-strong: #b56bf0;
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

        .primary-nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

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

        main {
            display: grid;
            min-height: calc(100vh - 78px);
            place-items: center;
            padding: 72px 24px;
            text-align: center;
        }

        .welcome { max-width: 720px; }

        .eyebrow {
            display: inline-block;
            margin-bottom: 18px;
            color: var(--accent);
            font-size: .78rem;
            font-weight: 750;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(2.8rem, 8vw, 5.7rem);
            line-height: .98;
            letter-spacing: -.075em;
        }

        h1 span { color: var(--accent); }

        .welcome p {
            max-width: 540px;
            margin: 24px auto 30px;
            color: var(--muted);
            font-size: clamp(1rem, 2vw, 1.15rem);
            line-height: 1.7;
        }

        .browse-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 20px;
            border-radius: 999px;
            color: #21152c;
            background: var(--accent);
            font-weight: 750;
            text-decoration: none;
            transition: transform .18s ease, background .18s ease;
        }

        .browse-link:hover { transform: translateY(-2px); background: #e5c9ff; }

        @media (max-width: 760px) {
            .site-header { flex-wrap: wrap; gap: 10px 16px; }
            .brand { margin-right: auto; }
            .primary-nav { order: 3; width: 100%; justify-content: center; }
            .primary-nav a { padding: 9px 11px; }
            .header-search { order: 2; width: min(230px, 46vw); min-width: 130px; margin-left: 0; }
            .login-link { order: 2; padding: 9px 12px; }
            main { min-height: calc(100vh - 122px); }
        }

        @media (max-width: 420px) {
            .site-header { padding-inline: 14px; }
            .primary-nav { gap: 0; }
            .primary-nav a { padding-inline: 9px; font-size: .88rem; }
            .header-search { width: 100%; order: 4; }
            .login-link { margin-left: auto; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="<?= htmlspecialchars($viewRouteBase . '/home', ENT_QUOTES, 'UTF-8') ?>" aria-label="Weebi:fy home">Weebi<span>:fy</span></a>

        <nav class="primary-nav" aria-label="Main navigation">
            <a href="<?= htmlspecialchars($viewRouteBase . '/anime', ENT_QUOTES, 'UTF-8') ?>">Anime</a>
            <a href="<?= htmlspecialchars($viewRouteBase . '/manga', ENT_QUOTES, 'UTF-8') ?>">Manga</a>
            <a href="<?= htmlspecialchars($viewRouteBase . '/products', ENT_QUOTES, 'UTF-8') ?>">Products</a>
        </nav>

        <form class="header-search" role="search" action="" method="get">
            <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <input type="search" name="q" placeholder="Search your collection" aria-label="Search your collection">
        </form>

        <a class="login-link" href="../app/src/Login">Log in</a>
    </header>

    <main>
        <section class="welcome" aria-labelledby="welcome-title">
            <span class="eyebrow">Your collection, your way</span>
            <h1 id="welcome-title">Find your <span>favorites.</span></h1>
            <p>Keep track of the anime, manga, and collectibles that make your shelves feel like yours.</p>
            <a class="browse-link" href="<?= htmlspecialchars($viewRouteBase . '/anime', ENT_QUOTES, 'UTF-8') ?>">Explore anime <span aria-hidden="true">→</span></a>
        </section>
    </main>
</body>
</html>

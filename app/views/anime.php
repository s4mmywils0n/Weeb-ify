<?php
$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 1000000]]
);
$page = $page === false || $page === null ? 1 : $page;
$searchQuery = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q'])
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171421">
    <title>Anime — Weebi:fy</title>
    <style>
        :root {
            color-scheme: dark;
            --ink: #f7f3ff;
            --muted: #b8b0c9;
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

        main {
            width: min(1120px, 100% - 40px);
            margin: 0 auto;
            padding: 58px 0 72px;
        }

        .page-heading {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }

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
            margin: 0;
            font-size: clamp(2.5rem, 6vw, 4.3rem);
            line-height: .98;
            letter-spacing: -.075em;
        }

        h1 span { color: var(--accent); }

        .page-number { margin: 0 0 5px; color: var(--muted); }

        .record-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .anime-card {
            display: block;
            overflow: hidden;
            border: 1px solid #ffffff12;
            border-radius: 16px;
            color: inherit;
            background: #211d2ccc;
            box-shadow: 0 8px 28px #00000020;
            text-decoration: none;
            transition: transform .18s ease, border-color .18s ease;
        }

        a.anime-card:hover, a.anime-card:focus-visible {
            transform: translateY(-3px);
            border-color: var(--accent);
            outline: none;
        }

        .poster {
            display: grid;
            width: 100%;
            aspect-ratio: 3 / 4;
            place-items: center;
            overflow: hidden;
            color: var(--accent);
            background: linear-gradient(145deg, #493354, #252131 70%);
            font-size: 1.1rem;
            font-weight: 800;
            text-align: center;
        }

        .poster img { width: 100%; height: 100%; object-fit: cover; }

        .card-copy { padding: 15px; }

        .card-copy h2 {
            overflow: hidden;
            margin: 0 0 8px;
            font-size: 1.02rem;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card-meta { margin: 0; color: var(--muted); font-size: .88rem; }

        .status-message {
            grid-column: 1 / -1;
            margin: 0;
            padding: 32px;
            border: 1px solid #ffffff12;
            border-radius: 16px;
            color: var(--muted);
            background: #211d2caa;
            text-align: center;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            margin-top: 30px;
        }

        .pagination a, .pagination button {
            display: inline-flex;
            min-width: 112px;
            justify-content: center;
            padding: 11px 16px;
            border: 1px solid #ffffff20;
            border-radius: 999px;
            color: var(--ink);
            background: #ffffff0a;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .pagination a:hover, .pagination button:hover:not(:disabled) {
            border-color: var(--accent);
            background: #ffffff12;
        }

        .pagination button:disabled { opacity: .4; cursor: not-allowed; }
        .pagination .page-count { min-width: 58px; color: var(--muted); text-align: center; }

        @media (max-width: 900px) {
            .record-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
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
            main { width: min(100% - 28px, 1120px); padding-top: 38px; }
            .record-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
            .card-copy { padding: 12px; }
            .page-heading { align-items: start; flex-direction: column; }
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
        <a class="brand" href="home.php" aria-label="Weebi:fy home">Weebi<span>:fy</span></a>

        <nav class="primary-nav" aria-label="Main navigation">
            <a href="anime.php" aria-current="page">Anime</a>
            <a href="manga.php">Manga</a>
            <a href="products.php">Products</a>
        </nav>

        <form class="header-search" role="search" action="" method="get">
            <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <input type="search" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search your collection" aria-label="Search your collection">
        </form>

        <a class="login-link" href="../app/src/Login">Log in</a>
    </header>

    <main>
        <div class="page-heading">
            <div>
                <span class="eyebrow">Your collection</span>
                <h1>Anime <span>favorites.</span></h1>
            </div>
            <p class="page-number" id="page-number" aria-live="polite">Page <?= (int) $page ?></p>
        </div>

        <section class="record-grid" id="anime-list" aria-label="Anime records" aria-live="polite">
            <p class="status-message">Loading anime…</p>
        </section>

        <nav class="pagination" aria-label="Anime pages">
            <a id="previous-page" href="#">Previous</a>
            <span class="page-count" id="page-count">Page <?= (int) $page ?></span>
            <button id="next-page" type="button" disabled>Next</button>
        </nav>
    </main>

    <script>
        const currentPage = <?= (int) $page ?>;
        const itemsPerPage = 20;
        const list = document.getElementById("anime-list");
        const previousLink = document.getElementById("previous-page");
        const nextButton = document.getElementById("next-page");
        const pageLabel = document.getElementById("page-count");

        function pageUrl(page) {
            const params = new URLSearchParams(window.location.search);
            params.set("page", String(page));
            return `anime.php?${params.toString()}`;
        }

        function addText(parent, tagName, className, text) {
            const element = document.createElement(tagName);
            if (className) element.className = className;
            element.textContent = text;
            parent.appendChild(element);
            return element;
        }

        function showMessage(message) {
            list.replaceChildren();
            addText(list, "p", "status-message", message);
        }

        function renderRecord(record) {
            const hasValidId = Number.isInteger(Number(record.id)) && Number(record.id) > 0;
            const card = document.createElement(hasValidId ? "a" : "article");
            card.className = "anime-card";
            if (hasValidId) {
                const params = new URLSearchParams();
                params.set("id", String(Number(record.id)));
                params.set("page", String(currentPage));
                const search = new URLSearchParams(window.location.search).get("q");
                if (search) params.set("q", search);
                card.href = `anime-id.php?${params.toString()}`;
                card.setAttribute("aria-label", `View details for ${record.title || "this anime"}`);
            }

            const poster = document.createElement("div");
            poster.className = "poster";
            const imageUrl = typeof record.image === "string" ? record.image.trim() : "";

            if (/^https?:\/\//i.test(imageUrl)) {
                const image = document.createElement("img");
                image.src = imageUrl;
                image.alt = record.title ? `${record.title} poster` : "Anime poster";
                image.loading = "lazy";
                image.onerror = () => {
                    image.remove();
                    poster.textContent = record.title || "Anime";
                };
                poster.appendChild(image);
            } else {
                poster.textContent = record.title || "Anime";
            }

            card.appendChild(poster);
            const copy = document.createElement("div");
            copy.className = "card-copy";
            addText(copy, "h2", "", record.title || "Untitled anime");

            const details = [];
            if (record.showtype) details.push(record.showtype);
            if (record.episodecount !== null && record.episodecount !== undefined) {
                details.push(`${record.episodecount} episodes`);
            }
            if (record.status) details.push(record.status);
            addText(copy, "p", "card-meta", details.join(" · ") || "Anime entry");

            card.appendChild(copy);
            list.appendChild(card);
        }

        previousLink.href = currentPage > 1 ? pageUrl(currentPage - 1) : "#";
        previousLink.setAttribute("aria-disabled", currentPage > 1 ? "false" : "true");
        if (currentPage <= 1) {
            previousLink.style.pointerEvents = "none";
            previousLink.style.opacity = ".4";
        }

        fetch(`../api.php/anime?page=${currentPage}`, {
            headers: { "Accept": "application/json" }
        })
            .then(async response => {
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.error || "Could not load anime records.");
                }
                if (!Array.isArray(result)) {
                    throw new Error("The API returned an unexpected response.");
                }
                return result;
            })
            .then(records => {
                if (records.length === 0) {
                    showMessage(currentPage === 1 ? "No anime records found yet." : "No anime on this page.");
                    return;
                }

                list.replaceChildren();
                records.forEach(renderRecord);
                nextButton.disabled = records.length < itemsPerPage;
                nextButton.addEventListener("click", () => {
                    window.location.href = pageUrl(currentPage + 1);
                });
            })
            .catch(error => showMessage(`Could not load anime: ${error.message}`));
    </script>
</body>
</html>

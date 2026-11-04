<?php
$recordId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$recordId = $recordId === false || $recordId === null ? null : $recordId;

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

$returnUrl = 'anime.php?' . http_build_query(array_filter([
    'page' => $page,
    'q' => $searchQuery !== '' ? $searchQuery : null,
]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171421">
    <title>Anime details — Weebi:fy</title>
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

        .detail-panel {
            display: grid;
            grid-template-columns: minmax(220px, 320px) minmax(0, 1fr);
            gap: 30px;
            padding: 22px;
            border: 1px solid #ffffff12;
            border-radius: 20px;
            background: #211d2ccc;
            box-shadow: 0 12px 38px #00000025;
        }

        .poster {
            display: grid;
            width: 100%;
            min-height: 300px;
            aspect-ratio: 3 / 4;
            place-items: center;
            overflow: hidden;
            border-radius: 13px;
            color: var(--accent);
            background: linear-gradient(145deg, #493354, #252131 70%);
            font-size: 1.2rem;
            font-weight: 800;
            text-align: center;
        }

        .poster img { width: 100%; height: 100%; object-fit: cover; }

        .synopsis {
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.7;
            white-space: pre-wrap;
        }

        .details-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 20px;
            margin: 0;
        }

        .detail-item { padding: 12px 0; border-top: 1px solid #ffffff12; min-width: 0; }
        .detail-item dt { margin-bottom: 5px; color: var(--muted); font-size: .78rem; text-transform: capitalize; }
        .detail-item dd { overflow-wrap: anywhere; margin: 0; line-height: 1.45; }
        .detail-item dd a { color: var(--accent); }

        .status-message {
            margin: 0;
            padding: 24px;
            border: 1px solid #ffffff12;
            border-radius: 16px;
            color: var(--muted);
            background: #211d2ccc;
            text-align: center;
        }

        @media (max-width: 760px) {
            .site-header { flex-wrap: wrap; gap: 10px 16px; }
            .brand { margin-right: auto; }
            .primary-nav { order: 3; width: 100%; justify-content: center; }
            .primary-nav a { padding: 9px 11px; }
            .header-search { order: 2; width: min(230px, 46vw); min-width: 130px; margin-left: 0; }
            .login-link { order: 2; padding: 9px 12px; }
            .detail-panel { grid-template-columns: minmax(180px, 260px) minmax(0, 1fr); gap: 22px; }
        }

        @media (max-width: 560px) {
            .site-header { padding-inline: 14px; }
            .primary-nav { gap: 0; }
            .primary-nav a { padding-inline: 9px; font-size: .88rem; }
            .header-search { width: 100%; order: 4; }
            .login-link { margin-left: auto; }
            main { width: min(100% - 28px, 1000px); padding-top: 35px; }
            .detail-panel { grid-template-columns: 1fr; padding: 15px; }
            .poster { width: min(100%, 340px); min-height: 0; margin: 0 auto; }
            .details-list { grid-template-columns: 1fr; }
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
        <a class="back-link" href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">← Back to anime</a>
        <span class="eyebrow">Anime details</span>
        <h1 id="record-title">Loading <span>anime…</span></h1>

        <section id="record-content" aria-live="polite">
            <p class="status-message">Loading anime details…</p>
        </section>
    </main>

    <script>
        const recordId = <?= $recordId === null ? 'null' : (int) $recordId ?>;
        const content = document.getElementById("record-content");
        const title = document.getElementById("record-title");

        function showError(message) {
            content.replaceChildren();
            const notice = document.createElement("p");
            notice.className = "status-message";
            notice.textContent = message;
            content.appendChild(notice);
            title.textContent = "Anime ";
            const accent = document.createElement("span");
            accent.textContent = "not found.";
            title.appendChild(accent);
        }

        function safeImageUrl(value) {
            return typeof value === "string" && /^https?:\/\//i.test(value.trim())
                ? value.trim()
                : "";
        }

        function renderRecord(record) {
            title.replaceChildren();
            title.append(document.createTextNode(record.title || "Untitled "));
            const accent = document.createElement("span");
            title.appendChild(accent);

            content.replaceChildren();
            const panel = document.createElement("article");
            panel.className = "detail-panel";

            const poster = document.createElement("div");
            poster.className = "poster";
            const imageUrl = safeImageUrl(record.image);
            if (imageUrl) {
                const image = document.createElement("img");
                image.src = imageUrl;
                image.alt = record.title ? `${record.title} poster` : "Anime poster";
                image.onerror = () => {
                    image.remove();
                    poster.textContent = record.title || "Anime";
                };
                poster.appendChild(image);
            } else {
                poster.textContent = record.title || "Anime";
            }
            panel.appendChild(poster);

            const information = document.createElement("div");
            if (record.synopsis) {
                const synopsis = document.createElement("p");
                synopsis.className = "synopsis";
                synopsis.textContent = record.synopsis;
                information.appendChild(synopsis);
            }

            const details = document.createElement("dl");
            details.className = "details-list";
            const hiddenFields = new Set([
                "id",
                "anime_id",
                "slug",
                "image",
                "kitsu_url",
                "title",
                "synopsis"
            ]);

            Object.entries(record).forEach(([key, value]) => {
                if (hiddenFields.has(key)) return;

                const item = document.createElement("div");
                item.className = "detail-item";
                const label = document.createElement("dt");
                label.textContent = key.replaceAll("_", " ");
                const detailValue = document.createElement("dd");

                detailValue.textContent = value === null || value === "" ? "—" : String(value);

                item.append(label, detailValue);
                details.appendChild(item);
            });

            information.appendChild(details);
            panel.appendChild(information);
            content.appendChild(panel);
        }

        if (!recordId) {
            showError("No valid anime ID was provided.");
        } else {
            fetch(`../api.php/anime/${recordId}`, {
                headers: { "Accept": "application/json" }
            })
                .then(async response => {
                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(result.error || "Could not load this anime record.");
                    }
                    return result;
                })
                .then(renderRecord)
                .catch(error => showError(error.message));
        }
    </script>
</body>
</html>

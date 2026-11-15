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
   
    <link rel="stylesheet" href="site-chrome.css">
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
    <footer class="site-footer">
        <p>Weebi:fy — Your collection, your way.</p>
        <nav class="footer-nav" aria-label="Footer navigation">
            <a href="anime.php">Anime</a>
            <a href="manga.php">Manga</a>
            <a href="products.php">Products</a>
        </nav>
    </footer>

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

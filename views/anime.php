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
   
    <link rel="stylesheet" href="site-chrome.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="home.php" aria-label="Weebi:fy home">Weebi<span>:fy</span></a>

        <nav class="primary-nav" aria-label="Main navigation">
            <a href="anime.php" aria-current="page">Anime</a>
            <a href="manga.php">Manga</a>
            <a href="products.php">Products</a>
             <a href="favorites.php">Favorites</a>
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
                <h1>Anime <span>List.</span></h1>
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

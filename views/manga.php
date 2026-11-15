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
    <title>Manga — Weebi:fy</title>
   
    <link rel="stylesheet" href="site-chrome.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="home.php" aria-label="Weebi:fy home">Weebi<span>:fy</span></a>

        <nav class="primary-nav" aria-label="Main navigation">
            <a href="anime.php">Anime</a>
            <a href="manga.php" aria-current="page">Manga</a>
            <a href="products.php">Products</a>
             <a href="favorites.php">Favorites</a>
        </nav>

        <form class="header-search" role="search" action="" method="get">
            <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <input type="search" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search manga" aria-label="Search manga">
        </form>

        <a class="login-link" href="../app/src/Login">Log in</a>
    </header>

    <main>
        <div class="page-heading">
            <div>
                <span class="eyebrow">Your manga collection</span>
                <h1>Manga <span>shelf.</span></h1>
            </div>
            <p class="page-number" id="page-number" aria-live="polite">Page <?= (int) $page ?></p>
        </div>

        <section class="record-grid" id="manga-list" aria-label="Manga collection" aria-live="polite">
            <p class="status-message">Loading manga…</p>
        </section>

        <nav class="pagination" aria-label="Manga pages">
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
        const list = document.getElementById("manga-list");
        const previousLink = document.getElementById("previous-page");
        const nextButton = document.getElementById("next-page");

        function pageUrl(page) {
            const params = new URLSearchParams(window.location.search);
            params.set("page", String(page));
            return `manga.php?${params.toString()}`;
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
            const card = document.createElement("article");
            card.className = "manga-card";

            const poster = document.createElement("div");
            poster.className = "poster";
            const posterCopy = document.createElement("div");
            posterCopy.className = "poster-copy";
            addText(posterCopy, "span", "poster-label", "Manga");
            addText(posterCopy, "span", "poster-title", record.title || "Untitled manga");
            poster.appendChild(posterCopy);

            card.appendChild(poster);
            const copy = document.createElement("div");
            copy.className = "card-copy";
            addText(copy, "h2", "", record.title || "Untitled manga");

            const author = typeof record.author === "string" && record.author.trim()
                ? record.author.trim()
                : "Author unknown";
            addText(copy, "p", "card-meta", author);

            const details = [];
            if (record.number_of_volumes !== null && record.number_of_volumes !== undefined) {
                details.push(`${record.number_of_volumes} ${Number(record.number_of_volumes) === 1 ? "volume" : "volumes"}`);
            }
            if (record.number_of_chapters !== null && record.number_of_chapters !== undefined) {
                details.push(`${record.number_of_chapters} ${Number(record.number_of_chapters) === 1 ? "chapter" : "chapters"}`);
            }
            if (record.year_published) details.push(String(record.year_published));
            if (record.status) details.push(record.status);
            if (record.genre) details.push(record.genre);
            if (details.length > 0) addText(copy, "p", "card-meta", details.join(" · "));

            card.appendChild(copy);
            list.appendChild(card);
        }

        previousLink.href = currentPage > 1 ? pageUrl(currentPage - 1) : "#";
        previousLink.setAttribute("aria-disabled", currentPage > 1 ? "false" : "true");
        if (currentPage <= 1) {
            previousLink.style.pointerEvents = "none";
            previousLink.style.opacity = ".4";
        }

        const searchParams = new URLSearchParams({ page: String(currentPage) });
        const searchQuery = new URLSearchParams(window.location.search).get("q");
        if (searchQuery && searchQuery.trim()) searchParams.set("q", searchQuery.trim());

        fetch(`../api.php/manga?${searchParams.toString()}`, {
            headers: { "Accept": "application/json" }
        })
            .then(async response => {
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.error || "Could not load manga records.");
                }
                if (!Array.isArray(result)) {
                    throw new Error("The API returned an unexpected response.");
                }
                return result;
            })
            .then(records => {
                if (records.length === 0) {
                    showMessage(searchQuery
                        ? "No manga matched your search."
                        : currentPage === 1
                            ? "No manga records found yet."
                            : "No manga on this page.");
                    return;
                }

                list.replaceChildren();
                records.forEach(renderRecord);
                nextButton.disabled = records.length < itemsPerPage;
                nextButton.addEventListener("click", () => {
                    window.location.href = pageUrl(currentPage + 1);
                });
            })
            .catch(error => showMessage(`Could not load manga: ${error.message}`));
    </script>
</body>
</html>
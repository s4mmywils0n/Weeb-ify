<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

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
<?php
$title = 'Manga — Weebi:fy';
$active = 'manga';
$searchAction = BASE_URL . '/manga';
$searchPlaceholder = 'Search manga';
require __DIR__ . '/partials/header.php';
?>

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
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const API_URL = <?= json_encode(API_URL) ?>;
        const currentPage = <?= (int) $page ?>;
        const itemsPerPage = 20;
        const list = document.getElementById("manga-list");
        const previousLink = document.getElementById("previous-page");
        const nextButton = document.getElementById("next-page");
         const pageLabel = document.getElementById("page-count");
        const pageHeading = document.getElementById("page-number");

           const urlParams = new URLSearchParams(window.location.search);
        const listParams = new URLSearchParams();
        ["q", "showtype", "agerating", "sort", "limit"].forEach(key => {
            const value = urlParams.get(key);
            if (value) listParams.set(key, value);
        });

          // ---- Where we are: the cursor for this page (from the previous Next/Previous click) ----
        // "page" is only the number shown to the user; the cursor decides which anime load.
        const after = urlParams.get("after");
        const before = urlParams.get("before");
        const pageNumber = (after || before) ? Math.max(1, parseInt(urlParams.get("page"), 10) || 1) : 1;
        pageLabel.textContent = `Page ${pageNumber}`;
        pageHeading.textContent = `Page ${pageNumber}`;

        // URL of this list at another position: { after: cursor } or { before: cursor }
        function listUrl(position, page) {
            const params = new URLSearchParams(listParams);
            Object.entries(position).forEach(([key, value]) => params.set(key, value));
            if (page > 1) params.set("page", String(page));
            return `${BASE_URL}/manga?${params.toString()}`;
        }

        function setLink(link, url) {
            link.href = url || "#";
            link.setAttribute("aria-disabled", url ? "false" : "true");
        }

        function pageUrl(page) {
            const params = new URLSearchParams(window.location.search);
            params.set("page", String(page));
            return `${BASE_URL}/manga?${params.toString()}`;
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
                // Keep the list position so "Back to anime" returns to this exact page
                const params = new URLSearchParams(listParams);
                if (after) params.set("after", after);
                if (before) params.set("before", before);
                if (pageNumber > 1) params.set("page", String(pageNumber));
                const query = params.toString();
                card.href = `${BASE_URL}/manga/${Number(record.id)}${query ? "?" + query : ""}`;
                card.setAttribute("aria-label", `View details for ${record.title || "this manga"}`);
            }

    const poster = document.createElement("div");
    poster.className = "poster";

    const imageUrl = typeof record.image === "string" && /^https?:\/\//i.test(record.image.trim())
        ? record.image.trim()
        : "";

    if (imageUrl) {
        const img = document.createElement("img");
        img.src = imageUrl;
        img.alt = record.title ? `${record.title} poster` : "Manga poster";
        img.loading = "lazy";
        img.onerror = () => {
            img.remove();
            const fallback = document.createElement("div");
            fallback.className = "poster-copy";
            addText(fallback, "span", "poster-label", "Manga");
            addText(fallback, "span", "poster-title", record.title || "Untitled manga");
            poster.appendChild(fallback);
        };
        poster.appendChild(img);
    } else {
        const posterCopy = document.createElement("div");
        posterCopy.className = "poster-copy";
        addText(posterCopy, "span", "poster-label", "Manga");
        addText(posterCopy, "span", "poster-title", record.title || "Untitled manga");
        poster.appendChild(posterCopy);
    }

    card.appendChild(poster);
    const copy = document.createElement("div");
    copy.className = "card-copy";
    addText(copy, "h2", "", record.title || "Untitled manga");

    const details = [];
    if (record.volumecount !== null && record.volumecount !== undefined) {
        details.push(`${record.volumecount} ${Number(record.volumecount) === 1 ? "volume" : "volumes"}`);
    }
    if (record.chaptercount !== null && record.chaptercount !== undefined) {
        details.push(`${record.chaptercount} ${Number(record.chaptercount) === 1 ? "chapter" : "chapters"}`);
    }
    if (record.status) details.push(record.status);
    if (record.mangatype) details.push(record.mangatype);
    if (record.serialization) details.push(record.serialization);
    if (details.length > 0) addText(copy, "p", "card-meta", details.join(" · "));

    if (Array.isArray(record.genres) && record.genres.length > 0) {
        addText(copy, "p", "card-meta", record.genres.join(" · "));
    }

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

        fetch(`${API_URL}/manga?${searchParams.toString()}`, {
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
<?php require __DIR__ . '/partials/footer.php'; ?>

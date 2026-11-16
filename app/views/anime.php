<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

$searchQuery = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q'])
    : '';

// Filter, sort and page-size options (shared with api.php). Unknown values are ignored here;
// the API is what actually enforces them.
$animeFilters = require dirname(__DIR__) . '/config/anime-filters.php';

$selected = [];
foreach (['showtype', 'agerating'] as $key) {
    $value = $_GET[$key] ?? '';
    $selected[$key] = is_string($value) && array_key_exists($value, $animeFilters[$key]['options']) ? $value : '';
}

$sortValue = $_GET['sort'] ?? '';
$selected['sort'] = is_string($sortValue) && $sortValue !== 'default' && isset($animeFilters['sort'][$sortValue])
    ? $sortValue
    : '';

$limitValue = filter_var($_GET['limit'] ?? null, FILTER_VALIDATE_INT);
$selected['limit'] = in_array($limitValue, $animeFilters['limit']['options'], true)
    ? $limitValue
    : $animeFilters['limit']['default'];

$rawCategories = $_GET['category'] ?? [];
$rawCategories = array_filter(is_array($rawCategories) ? $rawCategories : [$rawCategories], 'is_string');
$selected['category'] = array_values(array_intersect($animeFilters['category']['options'], $rawCategories));

$hasFilters = $selected['showtype'] !== '' || $selected['agerating'] !== '' || $selected['category'] !== [];
$hasChanges = $hasFilters || $selected['sort'] !== '' || $selected['limit'] !== $animeFilters['limit']['default'];

$e = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$title = 'Anime — Weebi:fy';
$active = 'anime';
require __DIR__ . '/partials/header.php';
?>

    <main>
        <div class="page-heading">
            <div>
                <span class="eyebrow">Your collection</span>
                <h1>Anime <span>favorites.</span></h1>
            </div>
            <p class="page-number" id="page-number" aria-live="polite"></p>
        </div>

        <?php if ($searchQuery !== ''):
            // "Clear search" keeps the filters, sort and page size; only drops the search text
            $clearParams = $_GET;
            unset($clearParams['q'], $clearParams['page'], $clearParams['after'], $clearParams['before']);
            $clearQuery = preg_replace('/%5B\d+%5D=/', '%5B%5D=', http_build_query($clearParams));
        ?>
            <p class="search-summary">
                <span>Results for <strong>“<?= $e($searchQuery) ?>”</strong></span>
                <a href="<?= BASE_URL ?>/anime<?= $clearQuery !== '' ? '?' . $e($clearQuery) : '' ?>">Clear search</a>
            </p>
        <?php endif; ?>

        <!-- Filters, sorting and page size. Changing any of them goes back to the first page. -->
        <form class="filter-bar" id="filter-form" action="<?= BASE_URL ?>/anime" method="get" aria-label="Filter and sort anime">
            <?php if ($searchQuery !== ''): ?>
                <input type="hidden" name="q" value="<?= $e($searchQuery) ?>">
            <?php endif; ?>

            <div class="filter-row">
                <?php foreach (['showtype', 'agerating'] as $filter): ?>
                    <label class="filter-field">
                        <span><?= $e($animeFilters[$filter]['label']) ?></span>
                        <select name="<?= $filter ?>">
                            <option value="">All</option>
                            <?php foreach ($animeFilters[$filter]['options'] as $value => $label): ?>
                                <option value="<?= $e($value) ?>"<?= $selected[$filter] === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>

                <label class="filter-field">
                    <span>Sort by</span>
                    <select name="sort">
                        <option value="">Default</option>
                        <?php foreach ($animeFilters['sort'] as $value => $option): ?>
                            <?php if ($value === 'default') continue; ?>
                            <option value="<?= $e($value) ?>"<?= $selected['sort'] === $value ? ' selected' : '' ?>><?= $e($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="filter-field filter-field-small">
                    <span>Per page</span>
                    <select name="limit">
                        <?php foreach ($animeFilters['limit']['options'] as $option): ?>
                            <option value="<?= (int) $option ?>"<?= $selected['limit'] === $option ? ' selected' : '' ?>><?= (int) $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="filter-actions">
                    <button type="submit" class="filter-apply">Apply</button>
                    <?php if ($hasChanges): ?>
                        <a class="filter-clear" href="<?= BASE_URL ?>/anime<?= $searchQuery !== '' ? '?q=' . $e(rawurlencode($searchQuery)) : '' ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </div>

            <fieldset class="category-filter">
                <legend><?= $e($animeFilters['category']['label']) ?> <small>(anime must have every one you tick)</small></legend>
                <div class="category-options">
                    <?php foreach ($animeFilters['category']['options'] as $category): ?>
                        <label class="category-chip">
                            <input type="checkbox" name="category[]" value="<?= $e($category) ?>"<?= in_array($category, $selected['category'], true) ? ' checked' : '' ?>>
                            <span><?= $e($category) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </form>

        <section class="record-grid" id="anime-list" aria-label="Anime records" aria-live="polite">
            <p class="status-message">Loading anime…</p>
        </section>

        <nav class="pagination" aria-label="Anime pages">
            <a id="previous-page" href="#" aria-disabled="true">Previous</a>
            <form class="page-count page-jump" id="page-jump" novalidate hidden>
                <label for="page-input">Page</label>
                <input type="number" id="page-input" name="page" min="1" step="1" inputmode="numeric">
                <span id="page-total"></span>
            </form>
            <a id="next-page" href="#" aria-disabled="true">Next</a>
        </nav>
    </main>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const API_URL = <?= json_encode(API_URL) ?>;
        const list = document.getElementById("anime-list");
        const previousLink = document.getElementById("previous-page");
        const nextLink = document.getElementById("next-page");
        const pageJump = document.getElementById("page-jump");
        const pageInput = document.getElementById("page-input");
        const pageTotal = document.getElementById("page-total");
        const pageHeading = document.getElementById("page-number");

        // ---- What is being shown: search, filters, sort and page size from the URL ----
        const urlParams = new URLSearchParams(window.location.search);
        const listParams = new URLSearchParams();
        ["q", "showtype", "agerating", "sort", "limit"].forEach(key => {
            const value = urlParams.get(key);
            if (value) listParams.set(key, value);
        });
        urlParams.getAll("category[]").forEach(value => listParams.append("category[]", value));
        const hasFilters = ["showtype", "agerating", "category[]"].some(key => listParams.has(key));

        // ---- Where we are ----
        // Next/Previous use a cursor (after/before). A typed-in page number uses ?page=N.
        // The page number shown always comes from the API (X-Page), so it matches what loaded.
        const after = urlParams.get("after");
        const before = urlParams.get("before");
        const requestedPage = (after || before) ? null : urlParams.get("page");

        function showPageNumber(page, totalPages) {
            pageHeading.textContent = page ? `Page ${page}` : "";
            pageJump.hidden = !page;
            if (!page) return;
            pageInput.value = String(page);
            pageInput.max = String(totalPages || page);
            pageInput.style.width = `${String(totalPages || page).length + 3}ch`;
            pageTotal.textContent = totalPages ? `of ${totalPages}` : "";
        }

        // Typing a page number and pressing Enter (or leaving the box) jumps to that page
        function jumpToPage() {
            const page = parseInt(pageInput.value, 10);
            const max = parseInt(pageInput.max, 10) || 1;
            if (!Number.isInteger(page) || page < 1) {
                pageInput.value = pageInput.defaultValue;
                return;
            }
            const target = Math.min(page, max);
            if (String(target) === pageInput.defaultValue) {
                pageInput.value = pageInput.defaultValue;
                return;
            }
            window.location.href = listUrl(target > 1 ? { page: target } : {});
        }
        pageJump.addEventListener("submit", event => {
            event.preventDefault();
            jumpToPage();
        });
        pageInput.addEventListener("change", jumpToPage);

        // URL of this list at another position: { after: cursor } or { before: cursor }
        function listUrl(position) {
            const params = new URLSearchParams(listParams);
            Object.entries(position).forEach(([key, value]) => params.set(key, value));
            return `${BASE_URL}/anime?${params.toString()}`;
        }

        function setLink(link, url) {
            link.href = url || "#";
            link.setAttribute("aria-disabled", url ? "false" : "true");
        }

        // ---- Filter form: apply straight away; empty choices are left out of the URL ----
        const filterForm = document.getElementById("filter-form");
        function submitFilters() {
            filterForm.querySelectorAll("select, input[type=hidden]").forEach(field => {
                field.disabled = field.value === "";
            });
            filterForm.submit();
        }
        filterForm.addEventListener("submit", event => {
            event.preventDefault();
            submitFilters();
        });
        filterForm.querySelectorAll("select").forEach(select => {
            select.addEventListener("change", submitFilters);
        });
        // Short pause after ticking a category, so several can be ticked before the page reloads
        let categoryTimer = null;
        filterForm.querySelectorAll("input[type=checkbox]").forEach(box => {
            box.addEventListener("change", () => {
                clearTimeout(categoryTimer);
                categoryTimer = setTimeout(submitFilters, 700);
            });
        });

        // ---- Cards ----
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
                if (requestedPage) params.set("page", requestedPage);
                const query = params.toString();
                card.href = `${BASE_URL}/anime/${Number(record.id)}${query ? "?" + query : ""}`;
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
            const showType = record.show_type;
            const episodeCount = record.episode_count;
            if (showType) details.push(showType);
            if (episodeCount !== null && episodeCount !== undefined) {
                details.push(`${episodeCount} episodes`);
            }
            if (record.status) details.push(record.status);
            addText(copy, "p", "card-meta", details.join(" · ") || "Anime entry");

            card.appendChild(copy);
            list.appendChild(card);
        }

        // ---- Load this page from the API ----
        const apiParams = new URLSearchParams(listParams);
        if (after) apiParams.set("after", after);
        else if (before) apiParams.set("before", before);
        else if (requestedPage) apiParams.set("page", requestedPage);

        fetch(`${API_URL}/anime?${apiParams.toString()}`, {
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
                return {
                    records: result,
                    next: response.headers.get("X-Next-Cursor"),
                    prev: response.headers.get("X-Prev-Cursor"),
                    page: parseInt(response.headers.get("X-Page"), 10) || null,
                    totalPages: parseInt(response.headers.get("X-Total-Pages"), 10) || null,
                };
            })
            .then(({ records, next, prev, page, totalPages }) => {
                showPageNumber(page, totalPages);
                if (page) pageInput.defaultValue = String(page);
                setLink(nextLink, next ? listUrl({ after: next }) : null);
                setLink(previousLink, prev ? listUrl({ before: prev }) : null);

                if (records.length === 0) {
                    const searchText = listParams.get("q");
                    showMessage(searchText
                        ? `No anime match “${searchText}”${hasFilters ? " with these filters" : ""}.`
                        : hasFilters ? "No anime match these filters." : "No anime records found yet.");
                    return;
                }

                list.replaceChildren();
                records.forEach(renderRecord);
            })
            .catch(error => showMessage(`Could not load anime: ${error.message}`));
    </script>
<?php require __DIR__ . '/partials/footer.php'; ?>

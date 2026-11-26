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

$mangaFilters = require dirname(__DIR__) . '/config/manga-filters.php';
$selected = [];
foreach (['mangatype', 'agerating'] as $key) {
    $value = $_GET[$key] ?? '';
    $selected[$key] = is_string($value) && array_key_exists($value, $mangaFilters[$key]['options']) ? $value : '';
}

$sortValue = $_GET['sort'] ?? '';
$selected['sort'] = is_string($sortValue) && $sortValue !== 'default' && isset($mangaFilters['sort'][$sortValue])
    ? $sortValue
    : '';

$limitValue = filter_var($_GET['limit'] ?? null, FILTER_VALIDATE_INT);
$selected['limit'] = in_array($limitValue, $mangaFilters['limit']['options'], true)
    ? $limitValue
    : $mangaFilters['limit']['default'];

$rawCategories = $_GET['category'] ?? [];
$rawCategories = array_filter(is_array($rawCategories) ? $rawCategories : [$rawCategories], 'is_string');
$selected['category'] = array_values(array_intersect($mangaFilters['category']['options'], $rawCategories));

$hasFilters = $selected['mangatype'] !== '' || $selected['agerating'] !== '' || $selected['category'] !== [];
$hasChanges = $hasFilters || $selected['sort'] !== '' || $selected['limit'] !== $mangaFilters['limit']['default'];
$e = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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

        <form class="filter-bar" id="filter-form" action="<?= BASE_URL ?>/manga" method="get" aria-label="Filter and sort manga">
            <?php if ($searchQuery !== ''): ?>
                <input type="hidden" name="q" value="<?= $e($searchQuery) ?>">
            <?php endif; ?>

            <div class="filter-row">
                <?php foreach (['mangatype', 'agerating'] as $filter): ?>
                    <label class="filter-field">
                        <span><?= $e($mangaFilters[$filter]['label']) ?></span>
                        <select name="<?= $filter ?>">
                            <option value="">All</option>
                            <?php foreach ($mangaFilters[$filter]['options'] as $value => $label): ?>
                                <option value="<?= $e($value) ?>"<?= $selected[$filter] === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>

                <label class="filter-field">
                    <span>Sort by</span>
                    <select name="sort">
                        <option value="">Default</option>
                        <?php foreach ($mangaFilters['sort'] as $value => $option): ?>
                            <?php if ($value === 'default') continue; ?>
                            <option value="<?= $e($value) ?>"<?= $selected['sort'] === $value ? ' selected' : '' ?>><?= $e($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="filter-field filter-field-small">
                    <span>Per page</span>
                    <select name="limit">
                        <?php foreach ($mangaFilters['limit']['options'] as $option): ?>
                            <option value="<?= (int) $option ?>"<?= $selected['limit'] === $option ? ' selected' : '' ?>><?= (int) $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="filter-actions">
                    <button type="submit" class="filter-apply">Apply</button>
                    <?php if ($hasChanges): ?>
                        <a class="filter-clear" href="<?= BASE_URL ?>/manga<?= $searchQuery !== '' ? '?q=' . $e(rawurlencode($searchQuery)) : '' ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </div>

            <fieldset class="category-filter">
                <legend><?= $e($mangaFilters['category']['label']) ?> <small>(manga must have every one you tick)</small></legend>
                <div class="category-options">
                    <?php foreach ($mangaFilters['category']['options'] as $category): ?>
                        <label class="category-chip">
                            <input type="checkbox" name="category[]" value="<?= $e($category) ?>"<?= in_array($category, $selected['category'], true) ? ' checked' : '' ?>>
                            <span><?= $e($category) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </form>

        <section class="record-grid" id="manga-list" aria-label="Manga collection" aria-live="polite">
            <p class="status-message">Loading manga…</p>
        </section>

        <nav class="pagination" aria-label="Manga pages">
            <a id="previous-page" href="#" aria-disabled="true">Previous</a>
            <span class="page-count" id="page-count"></span>
            <a id="next-page" href="#" aria-disabled="true">Next</a>
        </nav>
    </main>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const API_URL = <?= json_encode(API_URL) ?>;
        const list = document.getElementById("manga-list");
        const previousLink = document.getElementById("previous-page");
        const nextLink = document.getElementById("next-page");
        const pageLabel = document.getElementById("page-count");
        const pageHeading = document.getElementById("page-number");

        const urlParams = new URLSearchParams(window.location.search);
        const listParams = new URLSearchParams();
        ["q", "mangatype", "agerating", "sort", "limit"].forEach(key => {
            const value = urlParams.get(key);
            if (value) listParams.set(key, value);
        });
        urlParams.getAll("category[]").forEach(value => listParams.append("category[]", value));
        const hasFilters = ["mangatype", "agerating", "category[]"].some(key => listParams.has(key));

        // The cursor selects the records; page is only the number shown to the user.
        const after = urlParams.get("after");
        const before = urlParams.get("before");
        const pageNumber = (after || before) ? Math.max(1, parseInt(urlParams.get("page"), 10) || 1) : 1;
        pageLabel.textContent = `Page ${pageNumber}`;
        pageHeading.textContent = `Page ${pageNumber}`;

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
        let categoryTimer = null;
        filterForm.querySelectorAll("input[type=checkbox]").forEach(box => {
            box.addEventListener("change", () => {
                clearTimeout(categoryTimer);
                categoryTimer = setTimeout(submitFilters, 700);
            });
        });

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

            const volumeCount = record.volume_count ?? record.volumecount;
            const chapterCount = record.chapter_count ?? record.chaptercount;
            const mangaType = record.manga_type ?? record.mangatype;
            const details = [];
            if (volumeCount !== null && volumeCount !== undefined) {
                details.push(`${volumeCount} ${Number(volumeCount) === 1 ? "volume" : "volumes"}`);
            }
            if (chapterCount !== null && chapterCount !== undefined) {
                details.push(`${chapterCount} ${Number(chapterCount) === 1 ? "chapter" : "chapters"}`);
            }
            if (record.status) details.push(record.status);
            if (mangaType) details.push(mangaType);
            if (record.serialization) details.push(record.serialization);
            if (details.length > 0) addText(copy, "p", "card-meta", details.join(" · "));

            if (Array.isArray(record.genres) && record.genres.length > 0) {
                addText(copy, "p", "card-meta", record.genres.join(" · "));
            }

            card.appendChild(copy);
            list.appendChild(card);
        }

        const apiParams = new URLSearchParams(listParams);
        if (after) apiParams.set("after", after);
        else if (before) apiParams.set("before", before);

        fetch(`${API_URL}/manga?${apiParams.toString()}`, {
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
                return {
                    records: result,
                    next: response.headers.get("X-Next-Cursor"),
                    prev: response.headers.get("X-Prev-Cursor"),
                };
            })
            .then(({ records, next, prev }) => {
                setLink(nextLink, next ? listUrl({ after: next }, pageNumber + 1) : null);
                setLink(previousLink, prev ? listUrl({ before: prev }, pageNumber - 1) : null);

                if (records.length === 0) {
                    showMessage(hasFilters || listParams.has("q")
                        ? "No manga match these filters."
                        : "No manga records found yet.");
                    return;
                }

                list.replaceChildren();
                records.forEach(renderRecord);
            })
            .catch(error => showMessage(`Could not load manga: ${error.message}`));
    </script>
<?php require __DIR__ . '/partials/footer.php'; ?>

<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

// $id comes from the route /anime/{id} (see public/index.php)
$recordId = filter_var(
    $id ?? null,
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

// "Back to anime" returns to the same page: search, filters, categories, sort, page size and cursor
$animeFilters = require dirname(__DIR__) . '/config/anime-filters.php';
$listState = ['q' => $searchQuery !== '' ? $searchQuery : null];
foreach (['showtype', 'agerating', 'sort'] as $key) {
    $choices = $key === 'sort' ? $animeFilters['sort'] : $animeFilters[$key]['options'];
    $value = $_GET[$key] ?? '';
    $listState[$key] = is_string($value) && array_key_exists($value, $choices) ? $value : null;
}
$limitValue = filter_var($_GET['limit'] ?? null, FILTER_VALIDATE_INT);
$listState['limit'] = in_array($limitValue, $animeFilters['limit']['options'], true) ? $limitValue : null;
$rawCategories = $_GET['category'] ?? [];
$rawCategories = array_filter(is_array($rawCategories) ? $rawCategories : [$rawCategories], 'is_string');
$listState['category'] = array_values(array_intersect($animeFilters['category']['options'], $rawCategories)) ?: null;
// Cursors are opaque (the API checks them); only allow their URL-safe characters here
foreach (['after', 'before'] as $key) {
    $value = $_GET[$key] ?? '';
    $listState[$key] = is_string($value) && preg_match('/\A[A-Za-z0-9_-]{1,300}\z/', $value) ? $value : null;
}
$listState['page'] = ($listState['after'] || $listState['before']) && $page > 1 ? $page : null;
// category[0]=..&category[1]=..  ->  category[]=..&category[]=..  (what the anime page reads)
$returnQuery = preg_replace('/%5B\d+%5D=/', '%5B%5D=', http_build_query(array_filter($listState)));
$returnUrl = BASE_URL . '/anime' . ($returnQuery !== '' ? '?' . $returnQuery : '');

$envPath = dirname(__DIR__, 2) . '/.env';
$env = is_file($envPath)
    ? (parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [])
    : [];
$supabaseUrl = rtrim((string) ($env['url'] ?? ''), '/');
$supabaseKey = (string) ($env['key'] ?? '');

// anime-detail.php (before the <script>)
$favoriteUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
?>
<?php
$title = 'Anime details — Weebi:fy';
$active = 'anime';
require __DIR__ . '/partials/header.php';
?>

    <main>
        <a class="back-link" href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">← Back to anime</a>
        <br>
        <span class="eyebrow">Anime details</span>
        <h1 id="record-title">Loading <span>anime…</span></h1>
        <div class="favorite-control">
            <button type="button" id="favorite-toggle" class="favorite-toggle" hidden disabled>
                Add to favorites
            </button>
            <p id="favorite-message" class="favorite-message" role="status" aria-live="polite"></p>
            <?php if ($recordId !== null && currentUserIsAdmin()): ?>
            <a class="login-link login-link-secondary" href="<?= BASE_URL ?>/admin?type=anime&amp;edit=<?= (int) $recordId ?>">Edit (admin)</a>
            <?php endif; ?>
        </div>

        <section id="record-content" aria-live="polite">
            <p class="status-message">Loading anime details…</p>
        </section>
    </main>
    <script>
        const API_URL = <?= json_encode(API_URL) ?>;
        const recordId = <?= $recordId === null ? 'null' : (int) $recordId ?>;
        const content = document.getElementById("record-content");
        const BASE_URL  = <?= json_encode(BASE_URL) ?>;
        const loggedIn  = <?= currentUserId() !== null ? 'true' : 'false' ?>;
        const title = document.getElementById("record-title");
        const favoriteButton = document.getElementById("favorite-toggle");
        const favoriteMessage = document.getElementById("favorite-message");
        
        let currentAnimeId = null;
        let isFavorite = false;


       

        function setFavoriteButtonState() {
            favoriteButton.textContent = isFavorite ? "Remove from favorites" : "Add to favorites";
            favoriteButton.setAttribute("aria-pressed", String(isFavorite));
        }

async function loadFavoriteState(animeId) {
    if (!loggedIn) {
        favoriteButton.hidden = true;
        favoriteMessage.innerHTML =
            '<a href="' + BASE_URL + '/login">Log in</a> to save favorites.';
        return;
    }

    try {
        const res = await fetch(
            `${API_URL}/favorites?type=anime&anime_id=${encodeURIComponent(animeId)}`,
            { credentials: "same-origin", headers: { "Accept": "application/json" } }
        );
        if (!res.ok) throw new Error("Could not check favorites");
        const data = await res.json();
        isFavorite = !!data.isFavorite;
        setFavoriteButtonState();
        favoriteButton.hidden = false;
        favoriteButton.disabled = false;
    } catch (err) {
        favoriteButton.hidden = false;
        favoriteButton.disabled = true;
        favoriteMessage.textContent = err.message;
    }
}

favoriteButton.addEventListener("click", async () => {
    if (!currentAnimeId) return;
    favoriteButton.disabled = true;
    favoriteMessage.textContent = "";

    try {
        const url = isFavorite
            ? `${API_URL}/favorites?type=anime&anime_id=${encodeURIComponent(currentAnimeId)}`
            : `${API_URL}/favorites`;
        const options = {
            method: isFavorite ? "DELETE" : "POST",
            credentials: "same-origin",
            headers: { "Accept": "application/json", "Content-Type": "application/json" },
        };
        if (!isFavorite) options.body = JSON.stringify({ type: "anime", anime_id: currentAnimeId });

        const res = await fetch(url, options);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || "Request failed");

        isFavorite = !isFavorite;
        setFavoriteButtonState();
    } catch (err) {
        favoriteMessage.textContent = `Could not update favorites: ${err.message}`;
    } finally {
        favoriteButton.disabled = false;
    }
});

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
            currentAnimeId = record.anime_id == null ? null : String(record.anime_id);
            if (currentAnimeId) {
                favoriteButton.hidden = false;
                favoriteButton.disabled = true;
                loadFavoriteState(currentAnimeId);
            } else {
                favoriteButton.hidden = false;
                favoriteButton.disabled = true;
                favoriteMessage.textContent = "This anime does not have an ID that can be favorited.";
            }

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
            fetch(`${API_URL}/anime/${recordId}`, {
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
<?php require __DIR__ . '/partials/footer.php'; ?>

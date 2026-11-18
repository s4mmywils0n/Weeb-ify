<?php
if (!defined('BASE_URL')) { http_response_code(404); exit; }

$userId = filter_var($user ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false || $userId === null) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}
?>
<?php ob_start(); ?>
<?php $extraHead = ob_get_clean(); ?>
<?php
$title             = 'Favorites — Weebi:fy';
$active            = 'favorites';
$searchPlaceholder = 'Search favorites';
require __DIR__ . '/partials/header.php';
?>

<main>
    <a class="back-link" href="<?= BASE_URL ?>/">← Back to home</a>
    <br>
    <span class="eyebrow">Your library</span>
    <h1>Favorite <span>anime.</span></h1>
    <br>
    <section id="favorites-content" aria-live="polite">
        <p class="status-message">Loading favorites…</p>
    </section>
</main>
<div>
<script>
    const BASE_URL = <?= json_encode(BASE_URL) ?>;
    const API_URL  = <?= json_encode(API_URL) ?>;
    const content  = document.getElementById('favorites-content');

    function safeImageUrl(value) {
        return typeof value === 'string' && /^https?:\/\//i.test(value.trim())
            ? value.trim()
            : '';
    }

    function emptyStateHtml() {
        return 'You haven\'t favorited any anime yet. Browse the ' +
            `<a href="${BASE_URL}/anime" style="color: var(--accent);">anime collection</a> and add some.`;
    }

    function buildCard(fav) {
        const title        = fav.title || 'Untitled anime';
        const image        = safeImageUrl(fav.image);
        const episodeCount = fav.episode_count;
        const showType     = fav.show_type;
        const status       = fav.status;
        const animeId      = fav.anime_id;
        const detailId     = fav.id ?? fav.anime_id ?? '';
        const detailHref   = `${BASE_URL}/anime/${encodeURIComponent(detailId)}`;

        const metaParts = [showType, episodeCount ? `${episodeCount} episodes` : null, status].filter(Boolean);
        const meta = metaParts.length ? metaParts.join(' · ') : 'No details';

        const card = document.createElement('article');
        card.className = 'favorite-card';
        card.dataset.animeId = animeId ?? '';

        const posterLink = document.createElement('a');
        posterLink.href = detailHref;
        posterLink.className = 'favorite-poster-link';
        posterLink.setAttribute('aria-label', `View details for ${title}`);

        const poster = document.createElement('div');
        poster.className = 'favorite-poster';
        if (image) {
            const img = document.createElement('img');
            img.src = image;
            img.alt = `${title} poster`;
            img.loading = 'lazy';
            img.onerror = () => { img.remove(); poster.textContent = title; };
            poster.appendChild(img);
        } else {
            poster.textContent = title;
        }
        posterLink.appendChild(poster);

        const body = document.createElement('div');
        body.className = 'favorite-body';

        const h2 = document.createElement('h2');
        const titleLink = document.createElement('a');
        titleLink.href = detailHref;
        titleLink.style.color = 'inherit';
        titleLink.style.textDecoration = 'none';
        titleLink.textContent = title;
        h2.appendChild(titleLink);
        body.appendChild(h2);

        const metaP = document.createElement('p');
        metaP.textContent = meta;
        body.appendChild(metaP);

        const actions = document.createElement('div');
        actions.className = 'favorite-actions';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-favorite';
        removeBtn.dataset.animeId = animeId ?? '';
        removeBtn.textContent = 'Remove';
        actions.appendChild(removeBtn);
        body.appendChild(actions);

        card.append(posterLink, body);
        return card;
    }

    function render(records) {
        content.replaceChildren();

        if (records.length === 0) {
            const p = document.createElement('p');
            p.className = 'status-message';
            p.innerHTML = emptyStateHtml();
            content.appendChild(p);
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'favorites-grid';
        for (const fav of records) {
            grid.appendChild(buildCard(fav));
        }
        content.appendChild(grid);
    }

    function attachRemove() {
        content.querySelectorAll('.remove-favorite').forEach(button => {
            button.addEventListener('click', async () => {
                if (button.disabled) return;
                const animeId = button.dataset.animeId;
                if (!animeId) return;

                const card = button.closest('.favorite-card');
                button.disabled = true;
                button.textContent = 'Removing…';

                try {
                    const res = await fetch(
                        `${API_URL}/favorites?anime_id=${encodeURIComponent(animeId)}`,
                        {
                            method: 'DELETE',
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' },
                        }
                    );
                    const data = await res.json().catch(() => null);
                    if (!res.ok) throw new Error(data?.error || `Request failed (${res.status})`);

                    card?.remove();
                    if (!content.querySelector('.favorite-card')) {
                        const p = document.createElement('p');
                        p.className = 'status-message';
                        p.innerHTML = emptyStateHtml();
                        content.replaceChildren(p);
                    }
                } catch (err) {
                    button.disabled = false;
                    button.textContent = 'Remove';
                    alert(err.message);
                }
            });
        });
    }

    fetch(`${API_URL}/favorites`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(async response => {
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Could not load favorites.');
            if (!Array.isArray(result)) throw new Error('Unexpected API response.');
            return result;
        })
        .then(records => {
            render(records);
            attachRemove();
        })
        .catch(error => {
            content.replaceChildren();
            const p = document.createElement('p');
            p.className = 'status-message';
            p.setAttribute('role', 'alert');
            p.textContent = error.message === 'Login required'
                ? 'Please log in to see your favorites.'
                : `Could not load favorites: ${error.message}`;
            content.appendChild(p);
        });
</script>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
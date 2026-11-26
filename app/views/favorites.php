<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

// /profile/{user}/favorites passes the profile owner in as $user
$userId = filter_var($user ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false || $userId === null) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$title             = 'Favorites — Weebi:fy';
$active            = 'favorites';
$searchPlaceholder = 'Search favorites';
require __DIR__ . '/partials/header.php';
?>

    <main>
        <a class="back-link" href="<?= BASE_URL ?>/">← Back to home</a>
        <br>
        <span class="eyebrow">Your library</span>
        <h1>Your <span>favorites.</span></h1>

        <div class="favorites-tabs" role="tablist" aria-label="Favorite type">
            <button type="button" class="favorites-tab is-active" role="tab" id="tab-anime"
                    aria-selected="true" aria-controls="favorites-content"
                    data-type="anime">Favorite anime</button>
            <button type="button" class="favorites-tab" role="tab" id="tab-manga"
                    aria-selected="false" aria-controls="favorites-content"
                    data-type="manga" tabindex="-1">Favorite manga</button>
        </div>

        <section id="favorites-content" role="tabpanel" aria-labelledby="tab-anime" aria-live="polite">
            <p class="status-message">Loading favorites…</p>
        </section>
    </main>

    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const API_URL  = <?= json_encode(API_URL) ?>;

        const content = document.getElementById('favorites-content');
        const tabs = Array.from(document.querySelectorAll('.favorites-tab'));

        // active: which tab is on screen.
        // data:   one cache entry per tab (null = not loaded yet).
        // request: bumped on every switch, so a slow reply for an old tab can never
        //          overwrite the tab you are looking at now.
        const state = {
            active: 'anime',
            data: { anime: null, manga: null },
            request: 0,
        };

        function safeImageUrl(value) {
            return typeof value === 'string' && /^https?:\/\//i.test(value.trim())
                ? value.trim()
                : '';
        }

        function message(text) {
            content.replaceChildren();
            const p = document.createElement('p');
            p.className = 'status-message';
            p.textContent = text;
            content.appendChild(p);
        }

        function emptyState(type) {
            content.replaceChildren();

            const p = document.createElement('p');
            p.className = 'status-message';
            p.append(`You haven't favorited any ${type} yet. Browse the `);

            const link = document.createElement('a');
            link.href = `${BASE_URL}/${type}`;
            link.style.color = 'var(--accent)';
            link.textContent = `${type} collection`;

            p.append(link, ' and add some.');
            content.appendChild(p);
        }

        // ---- The tab itself ----
        function setActiveTab(type) {
            tabs.forEach(tab => {
                const on = tab.dataset.type === type;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.tabIndex = on ? 0 : -1;
            });
            content.setAttribute('aria-labelledby', `tab-${type}`);
        }

        // ---- Cards ----
        // These columns have been renamed more than once ("episode_count", "episode count",
        // "episodecount"), so read whichever spelling the row actually has.
        function pick(source, ...keys) {
            for (const key of keys) {
                const value = source[key];
                if (value !== null && value !== undefined && value !== '') {
                    return value;
                }
            }
            return null;
        }

        function cardMeta(type, favourite) {
            const parts = [];
            const status = pick(favourite, 'status');

            if (type === 'manga') {
                const mangaType = pick(favourite, 'manga_type');
                const volumes = pick(favourite, 'volume_count');
                const chapters = pick(favourite, 'chapter_count');

                if (mangaType) parts.push(mangaType);
                if (volumes !== null) {
                    parts.push(`${volumes} ${Number(volumes) === 1 ? 'volume' : 'volumes'}`);
                }
                if (chapters !== null) {
                    parts.push(`${chapters} ${Number(chapters) === 1 ? 'chapter' : 'chapters'}`);
                }
                const serialization = pick(favourite, 'serialization');
                if (serialization) parts.push(serialization);
            } else {
                const showType = pick(favourite, 'show_type');
                const episodes = pick(favourite, 'episode_count');

                if (showType) parts.push(showType);
                if (episodes !== null) parts.push(`${episodes} episodes`);
            }

            if (status) parts.push(status);

            const p = document.createElement('p');
            p.textContent = parts.length ? parts.join(' · ') : 'No details';
            return p;
        }

        function buildCard(type, favourite) {
            const title = favourite.title || `Untitled ${type}`;
            // anime_id / manga_id is the id the API stores; id is the row it joined to,
            // which is what the detail page routes on.
            const targetId = type === 'manga' ? favourite.manga_id : favourite.anime_id;
            const detailId = favourite.id ?? targetId ?? '';
            const detailHref = `${BASE_URL}/${type}/${encodeURIComponent(detailId)}`;

            const card = document.createElement('article');
            card.className = 'favorite-card';

            const posterLink = document.createElement('a');
            posterLink.href = detailHref;
            posterLink.className = 'favorite-poster-link';
            posterLink.setAttribute('aria-label', `View details for ${title}`);

            const poster = document.createElement('div');
            poster.className = 'favorite-poster';

            const image = safeImageUrl(favourite.image);
            if (image) {
                const img = document.createElement('img');
                img.src = image;
                img.alt = `${title} poster`;
                img.loading = 'lazy';
                img.onerror = () => {
                    img.remove();
                    poster.textContent = title;
                };
                poster.appendChild(img);
            } else {
                poster.textContent = title;
            }
            posterLink.appendChild(poster);

            const body = document.createElement('div');
            body.className = 'favorite-body';

            const heading = document.createElement('h2');
            const titleLink = document.createElement('a');
            titleLink.href = detailHref;
            titleLink.style.color = 'inherit';
            titleLink.style.textDecoration = 'none';
            titleLink.textContent = title;
            heading.appendChild(titleLink);

            body.append(heading, cardMeta(type, favourite));

            const actions = document.createElement('div');
            actions.className = 'favorite-actions';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove-favorite';
            remove.dataset.type = type;
            remove.dataset.targetId = targetId ?? '';
            remove.textContent = 'Remove';
            actions.appendChild(remove);

            body.appendChild(actions);
            card.append(posterLink, body);
            return card;
        }

        // ---- One renderer for whichever tab is active ----
        function render(type) {
            const records = state.data[type] || [];

            if (records.length === 0) {
                emptyState(type);
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'favorites-grid';
            records.forEach(favourite => grid.appendChild(buildCard(type, favourite)));
            content.replaceChildren(grid);
        }

        // ---- Loading ----
        async function fetchFavorites(type) {
            let response;

            try {
                response = await fetch(`${API_URL}/favorites?type=${type}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
            } catch {
                // Network / server down: fetch itself rejected, no response to read.
                throw new Error('Could not reach the favorites API.');
            }

            const data = await response.json().catch(() => null);
            if (!response.ok) {
                throw new Error((data && data.error) || `Request failed (${response.status})`);
            }
            if (!Array.isArray(data)) {
                throw new Error('The API returned an unexpected response.');
            }
            return data;
        }

        async function activate(type) {
            state.active = type;
            setActiveTab(type);

            // Already in memory: swap instantly instead of refetching.
            if (state.data[type] !== null) {
                render(type);
                return;
            }

            const token = ++state.request;
            message(`Loading favorite ${type}…`);

            try {
                const records = await fetchFavorites(type);
                if (token !== state.request) return; // a different tab was picked while this loaded
                state.data[type] = records;
            } catch (error) {
                if (token !== state.request) return;
                message(error.message === 'Login required'
                    ? 'Please log in to see your favorites.'
                    : error.message);
                return;
            }

            if (state.active === type) {
                render(type);
            }
        }

        // ---- Remove: one delegated listener, so re-rendering never leaves dead handlers ----
        content.addEventListener('click', async event => {
            const button = event.target.closest('.remove-favorite');
            if (!button || button.disabled) return;

            const type = button.dataset.type;
            const targetId = button.dataset.targetId;
            if (!type || !targetId) return;

            button.disabled = true;
            button.textContent = 'Removing…';

            try {
                const response = await fetch(
                    `${API_URL}/favorites?type=${type}&${type}_id=${encodeURIComponent(targetId)}`,
                    {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    }
                );

                const data = await response.json().catch(() => null);
                if (!response.ok) {
                    throw new Error((data && data.error) || `Request failed (${response.status})`);
                }

                // The cache stays the single source of truth: drop the row, then re-render.
                const idKey = type === 'manga' ? 'manga_id' : 'anime_id';
                state.data[type] = (state.data[type] || [])
                    .filter(favourite => String(favourite[idKey]) !== String(targetId));

                if (state.active === type) {
                    render(type);
                }
            } catch (error) {
                button.disabled = false;
                button.textContent = 'Remove';

                const card = button.closest('.favorite-card');
                const body = card && card.querySelector('.favorite-body');
                if (!body) return;

                let notice = body.querySelector('.favorite-error');
                if (!notice) {
                    notice = document.createElement('p');
                    notice.className = 'favorite-error';
                    body.appendChild(notice);
                }
                notice.textContent = `Could not remove: ${error.message}`;
            }
        });

        // ---- Tabs: click, plus left/right arrows like a real tablist ----
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab.dataset.type));

            tab.addEventListener('keydown', event => {
                const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
                if (step === 0) return;

                event.preventDefault();
                const next = tabs[(index + step + tabs.length) % tabs.length];
                next.focus();
                activate(next.dataset.type);
            });
        });

        activate('anime');
    </script>
<?php require __DIR__ . '/partials/footer.php'; ?>
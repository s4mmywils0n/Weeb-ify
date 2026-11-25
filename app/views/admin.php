<?php
// Only reachable through public/index.php (the router), behind AuthMiddleware + AdminMiddleware.
if (!defined('BASE_URL')) { http_response_code(404); exit; }

/*
 * Admin: list, search, create, edit and delete anime and manga.
 *
 * Everything goes through the API (api.php/anime, api.php/manga). This page has no
 * special powers: api.php checks is_admin again on every write, so the page being
 * hidden from non-admins is a convenience, not the protection.
 *
 * The editable fields come from app/config/catalogue-fields.php (the same allowlist
 * api.php enforces), so the form and the API can never disagree.
 *
 * Paging: anime uses the API's cursors (X-Next-Cursor / X-Prev-Cursor headers),
 * manga uses ?page=N (20 per page).
 *
 * Deep links: /admin?type=manga            open the manga tab
 *             /admin?type=anime&edit=12    open anime #12 in the editor
 */
$fieldsByType = require __DIR__ . '/../config/catalogue-fields.php';

$startType = ($_GET['type'] ?? '') === 'manga' ? 'manga' : 'anime';
$startEdit = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

ob_start(); ?>
<style>
    .admin-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
    .admin-search { display: flex; gap: 8px; flex: 1 1 320px; }
    .admin-search input,
    .admin-form input,
    .admin-form textarea {
        width: 100%; padding: 10px 14px; border: 1px solid #ffffff20; border-radius: 12px;
        color: var(--ink); background: #ffffff0a; font: inherit;
    }
    .admin-form textarea { min-height: 140px; resize: vertical; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th, .admin-table td { padding: 10px 12px; border-bottom: 1px solid #ffffff12; text-align: left; vertical-align: middle; }
    .admin-table th { color: var(--muted); font-size: 0.76rem; letter-spacing: 0.06em; text-transform: uppercase; }
    .admin-table td.admin-actions { white-space: nowrap; text-align: right; }
    .admin-table .muted { color: var(--muted); }
    .admin-btn { padding: 7px 14px; border: 1px solid #ffffff20; border-radius: 999px; color: var(--ink); background: #ffffff0a; font: inherit; font-weight: 700; cursor: pointer; }
    .admin-btn:hover { background: #ffffff18; }
    .admin-btn[disabled] { opacity: 0.5; cursor: default; }
    .admin-btn-danger { border-color: #ff7a9a55; color: #ffb3c4; }
    .admin-btn-primary { border: 0; color: #21152c; background: var(--accent); }
    .admin-btn-primary:hover { background: #e5c9ff; }
    .admin-editor { margin-top: 28px; padding: 22px; border: 1px solid #ffffff18; border-radius: 18px; background: #ffffff06; }
    .admin-form { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
    .admin-form label { display: grid; gap: 6px; color: var(--muted); font-size: 0.82rem; font-weight: 700; }
    .admin-form .wide { grid-column: 1 / -1; }
    .admin-form-actions { display: flex; gap: 10px; margin-top: 18px; }
    .admin-message { min-height: 1.4em; margin: 12px 0 0; }
    .admin-message.is-error { color: #ffb3c4; }
    .admin-message.is-ok { color: #b9f3c8; }
</style>
<?php $extraHead = ob_get_clean();

$title  = 'Admin — Weebi:fy';
$active = 'admin';
require __DIR__ . '/partials/header.php';
?>

<main>
    <span class="eyebrow">Admin</span>
    <h1>Edit the <span>catalogue.</span></h1>

    <div class="favorites-tabs" role="tablist" aria-label="Catalogue type">
        <button type="button" class="favorites-tab" role="tab" id="tab-anime" data-type="anime"
                aria-controls="admin-list">Anime</button>
        <button type="button" class="favorites-tab" role="tab" id="tab-manga" data-type="manga"
                aria-controls="admin-list">Manga</button>
    </div>

    <div class="admin-toolbar">
        <form class="admin-search" id="admin-search" role="search">
            <input type="search" id="admin-q" maxlength="100" placeholder="Search by title" aria-label="Search by title">
            <button type="submit" class="admin-btn">Search</button>
        </form>
        <button type="button" class="admin-btn admin-btn-primary" id="admin-new">+ New</button>
    </div>

    <section id="admin-list" role="tabpanel" aria-live="polite">
        <p class="status-message">Loading…</p>
    </section>

    <nav class="pagination" aria-label="Pages">
        <button type="button" id="admin-prev">← Previous</button>
        <span id="admin-page" class="muted"></span>
        <button type="button" id="admin-next">Next →</button>
    </nav>

    <section class="admin-editor" id="admin-editor" hidden aria-labelledby="admin-editor-title">
        <h2 id="admin-editor-title">Edit</h2>
        <form class="admin-form" id="admin-form" novalidate></form>
        <div class="admin-form-actions">
            <button type="submit" form="admin-form" class="admin-btn admin-btn-primary" id="admin-save">Save</button>
            <button type="button" class="admin-btn" id="admin-cancel">Cancel</button>
        </div>
        <p class="admin-message" id="admin-form-message" role="status"></p>
    </section>

    <p class="admin-message" id="admin-message" role="status"></p>
</main>

<script>
(() => {
    const API_URL = <?= json_encode(API_URL) ?>;
    const BASE_URL = <?= json_encode(BASE_URL) ?>;
    const CONFIG = <?= json_encode($fieldsByType) ?>;
    const PAGE_SIZE = 20; // anime: ?limit=20; manga: api.php's fixed 20 per page

    // Column types, so inputs and the values sent to the API have the right shape.
    const NUMBER_FIELDS = new Set(['episode_count', 'popularity_rank', 'chapter_count', 'volume_count']);
    const LONG_FIELDS = new Set(['synopsis']);
    const DATE_FIELDS = new Set(['created_at', 'end_date']);

    // anime pages by cursor: cursor = { after } or { before } for the page being loaded,
    // next / prev = the cursors the API sent back for the page on screen.
    const state = {
        type: <?= json_encode($startType) ?>, page: 1, q: '', editing: null, original: null,
        cursor: null, next: null, prev: null,
    };

    const list = document.getElementById('admin-list');
    const pageLabel = document.getElementById('admin-page');
    const prevBtn = document.getElementById('admin-prev');
    const nextBtn = document.getElementById('admin-next');
    const editor = document.getElementById('admin-editor');
    const editorTitle = document.getElementById('admin-editor-title');
    const form = document.getElementById('admin-form');
    const formMessage = document.getElementById('admin-form-message');
    const pageMessage = document.getElementById('admin-message');

    // ---------------------------------------------------------------- API helper
    // Returns parsed JSON; throws an Error whose message is safe to show.
    async function api(path, options = {}) {
        let response;
        try {
            response = await fetch(`${API_URL}${path}`, {
                credentials: 'same-origin',
                ...options,
                headers: { Accept: 'application/json', ...(options.headers || {}) },
            });
        } catch {
            throw new Error('Could not reach the API.');
        }
        const data = await response.json().catch(() => null);
        if (!response.ok) {
            let text = (data && data.error) || `Request failed (${response.status})`;
            if (response.status === 401) text = 'Your login expired. Log in again.';
            if (response.status === 403) text = 'Your account is not an admin.';
            if (data && data.requestId) text += ` (request ${data.requestId})`;
            const error = new Error(text);
            error.status = response.status;
            throw error;
        }
        return { data, headers: response.headers };
    }

    function show(el, text, kind) {
        el.textContent = text;
        el.classList.toggle('is-error', kind === 'error');
        el.classList.toggle('is-ok', kind === 'ok');
    }

    // ---------------------------------------------------------------- list
    async function loadList() {
        list.replaceChildren(Object.assign(document.createElement('p'), {
            className: 'status-message', textContent: 'Loading…',
        }));

        const params = new URLSearchParams();
        if (state.type === 'anime') {
            params.set('limit', String(PAGE_SIZE));
            if (state.cursor?.after) params.set('after', state.cursor.after);
            if (state.cursor?.before) params.set('before', state.cursor.before);
        } else {
            params.set('page', String(state.page));
        }
        if (state.q) params.set('q', state.q);

        let rows;
        try {
            const { data, headers } = await api(`/${state.type}?${params}`);
            rows = Array.isArray(data) ? data : [];
            state.next = headers.get('X-Next-Cursor');
            state.prev = headers.get('X-Prev-Cursor');
        } catch (error) {
            list.replaceChildren(Object.assign(document.createElement('p'), {
                className: 'status-message', textContent: error.message,
            }));
            return;
        }

        renderRows(rows);

        const hasNext = state.type === 'anime' ? state.next !== null : rows.length === PAGE_SIZE;
        prevBtn.disabled = state.type === 'anime' ? state.prev === null : state.page <= 1;
        nextBtn.disabled = !hasNext;
        pageLabel.textContent = `Page ${state.page}`;
    }

    function renderRows(rows) {
        if (rows.length === 0) {
            list.replaceChildren(Object.assign(document.createElement('p'), {
                className: 'status-message', textContent: state.q ? 'No titles match that search.' : 'Nothing here yet.',
            }));
            return;
        }

        const table = document.createElement('table');
        table.className = 'admin-table';
        const head = table.createTHead().insertRow();
        ['ID', 'Title', 'Status', ''].forEach(label => {
            const th = document.createElement('th');
            th.textContent = label;
            head.appendChild(th);
        });

        const body = table.createTBody();
        for (const row of rows) {
            const tr = body.insertRow();
            tr.insertCell().textContent = row.id;

            const link = document.createElement('a');
            link.href = `${BASE_URL}/${state.type}/${encodeURIComponent(row.id)}`;
            link.textContent = row.title || '(untitled)';
            link.style.color = 'inherit';
            tr.insertCell().appendChild(link);

            const status = tr.insertCell();
            status.className = 'muted';
            status.textContent = row.status || '—';

            const actions = tr.insertCell();
            actions.className = 'admin-actions';

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'admin-btn';
            edit.textContent = 'Edit';
            edit.addEventListener('click', () => openEditor(row.id));

            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'admin-btn admin-btn-danger';
            del.textContent = 'Delete';
            del.style.marginLeft = '8px';
            del.addEventListener('click', () => deleteRecord(row, del));

            actions.append(edit, del);
        }
        list.replaceChildren(table);
    }

    // ---------------------------------------------------------------- editor
    function buildForm(record) {
        form.replaceChildren();
        const { fields, required } = CONFIG[state.type];

        for (const field of fields) {
            const label = document.createElement('label');
            label.textContent = field + (required.includes(field) ? ' *' : '');

            const input = LONG_FIELDS.has(field)
                ? document.createElement('textarea')
                : document.createElement('input');
            input.name = field;
            if (input.tagName === 'INPUT') {
                input.type = NUMBER_FIELDS.has(field) ? 'number' : 'text';
                if (NUMBER_FIELDS.has(field)) input.step = '1';
                if (DATE_FIELDS.has(field)) input.placeholder = 'YYYY-MM-DD';
            }
            if (required.includes(field)) input.required = true;

            const value = record ? record[field] : null;
            input.value = value === null || value === undefined ? '' : String(value);

            if (LONG_FIELDS.has(field)) label.classList.add('wide');
            label.appendChild(input);
            form.appendChild(label);
        }
    }

    // Form value -> what the API should store: '' means NULL, numbers become numbers.
    function readField(field) {
        const raw = form.elements[field].value.trim();
        if (raw === '') return null;
        if (NUMBER_FIELDS.has(field)) {
            const n = Number(raw);
            return Number.isInteger(n) ? n : raw; // let the API reject non-integers
        }
        return raw;
    }

    function normalise(value) {
        return value === null || value === undefined || value === '' ? null : String(value);
    }

    async function openEditor(id) {
        show(formMessage, '');
        show(pageMessage, '');
        editor.hidden = false;

        if (id === null) {
            state.editing = null;
            state.original = null;
            editorTitle.textContent = `New ${state.type}`;
            buildForm(null);
            form.elements[CONFIG[state.type].fields[0]]?.focus();
            editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        editorTitle.textContent = `Loading ${state.type} #${id}…`;
        form.replaceChildren();
        try {
            const { data } = await api(`/${state.type}/${encodeURIComponent(id)}`);
            state.editing = data.id;
            state.original = data;
            editorTitle.textContent = `Edit ${state.type} #${data.id}: ${data.title || '(untitled)'}`;
            buildForm(data);
            editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            editorTitle.textContent = `Could not open ${state.type} #${id}`;
            show(formMessage, error.message, 'error');
        }
    }

    function closeEditor() {
        editor.hidden = true;
        state.editing = null;
        state.original = null;
        form.replaceChildren();
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const { fields, required } = CONFIG[state.type];

        for (const field of required) {
            if (readField(field) === null) {
                show(formMessage, `${field} is required.`, 'error');
                form.elements[field].focus();
                return;
            }
        }

        const creating = state.editing === null;
        const body = {};
        for (const field of fields) {
            const value = readField(field);
            if (creating) {
                if (value !== null) body[field] = value;
            } else if (normalise(value) !== normalise(state.original[field])) {
                body[field] = value; // PATCH: only what changed
            }
        }

        if (!creating && Object.keys(body).length === 0) {
            show(formMessage, 'Nothing changed.', '');
            return;
        }

        const save = document.getElementById('admin-save');
        save.disabled = true;
        show(formMessage, 'Saving…', '');
        try {
            const path = creating ? `/${state.type}` : `/${state.type}/${state.editing}`;
            const { data } = await api(path, {
                method: creating ? 'POST' : 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            closeEditor();
            show(pageMessage, `${creating ? 'Created' : 'Saved'} ${state.type} #${data.id}: ${data.title || ''}`, 'ok');
            loadList();
        } catch (error) {
            show(formMessage, error.message, 'error');
        } finally {
            save.disabled = false;
        }
    });

    async function deleteRecord(row, button) {
        if (!window.confirm(`Delete ${state.type} #${row.id} "${row.title || ''}"? This can't be undone.`)) {
            return;
        }
        button.disabled = true;
        try {
            await api(`/${state.type}/${encodeURIComponent(row.id)}`, { method: 'DELETE' });
            if (state.editing === row.id) closeEditor();
            show(pageMessage, `Deleted ${state.type} #${row.id}.`, 'ok');
            loadList();
        } catch (error) {
            button.disabled = false;
            show(pageMessage, error.message, 'error');
        }
    }

    // ---------------------------------------------------------------- wiring
    function setType(type) {
        state.type = type;
        state.page = 1;
        state.cursor = null;
        document.querySelectorAll('.favorites-tab').forEach(tab => {
            const on = tab.dataset.type === type;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.getElementById('admin-new').textContent = `+ New ${type}`;
        closeEditor();
        show(pageMessage, '');
        loadList();
    }

    document.querySelectorAll('.favorites-tab').forEach(tab =>
        tab.addEventListener('click', () => setType(tab.dataset.type)));

    document.getElementById('admin-search').addEventListener('submit', event => {
        event.preventDefault();
        state.q = document.getElementById('admin-q').value.trim();
        state.page = 1;
        state.cursor = null;
        loadList();
    });

    prevBtn.addEventListener('click', () => {
        if (state.page <= 1) return;
        if (state.type === 'anime') {
            if (!state.prev) return;
            state.cursor = { before: state.prev };
        }
        state.page--;
        loadList();
    });
    nextBtn.addEventListener('click', () => {
        if (state.type === 'anime') {
            if (!state.next) return;
            state.cursor = { after: state.next };
        }
        state.page++;
        loadList();
    });
    document.getElementById('admin-new').addEventListener('click', () => openEditor(null));
    document.getElementById('admin-cancel').addEventListener('click', closeEditor);

    setType(state.type);
    const startEdit = <?= json_encode($startEdit) ?>;
    if (startEdit !== null) openEditor(startEdit);
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php
$viewApiBaseUrl = isset($viewApiBaseUrl) ? $viewApiBaseUrl : '/api.php';
$viewRouteBase = isset($viewRouteBase) ? $viewRouteBase : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTTP API tester — Weebi:fy</title>
</head>
<body>
    <header><a href="<?= htmlspecialchars($viewRouteBase . '/home', ENT_QUOTES, 'UTF-8') ?>">Weebi<span>:fy</span></a></header>
    <main>
        <h1>HTTP API tester</h1>
        <p class="intro">Send a request to the anime or manga API and inspect the response.</p>
        <form id="request-form" class="panel" aria-label="API request builder">
            <div class="request-row">
                <label for="method">Method
                    <select id="method">
                        <option>GET</option>
                        <option>POST</option>
                        <option>PATCH</option>
                        <option>DELETE</option>
                    </select>
                </label>
                <label for="resource">Resource
                    <select id="resource"><option value="anime">Anime</option><option value="manga">Manga</option></select>
                </label>
                <label for="record-id">Record ID (optional)
                    <input id="record-id" type="number" min="1" step="1" placeholder="e.g. 12">
                </label>
            </div>
            <p class="hint">Use an ID for GET one, PATCH, and DELETE. Leave it blank for GET all or POST.</p>
            <label for="payload">JSON body (POST or PATCH)
                <textarea id="payload" spellcheck="false">{
  "title": "Example anime",
  "showtype": "TV",
  "agerating": "PG"
}</textarea>
            </label>
            <div class="actions">
                <button id="send" type="submit">Send request</button>
                <span id="endpoint"><?= htmlspecialchars($viewApiBaseUrl, ENT_QUOTES, 'UTF-8') ?>/anime</span>
            </div>
            <section class="response" aria-live="polite">
                <div class="response-heading"><h2>Response</h2><span id="status">No request sent</span></div>
                <pre id="response">The response will appear here.</pre>
            </section>
        </form>
    </main>
    <script>
        const apiBase = <?= json_encode($viewApiBaseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const requestForm = document.querySelector('#request-form');
        const methodInput = document.querySelector('#method')
        const resourceInput = document.querySelector('#resource')
        const idInput = document.querySelector('#record-id')
        const payloadInput = document.querySelector('#payload')
        const endpointLabel = document.querySelector('#endpoint')
        const statusLabel = document.querySelector('#status')
        const responseOutput = document.querySelector('#response')
        const sendButton = document.querySelector('#send')

        function currentEndpoint() {
            const resource = resourceInput.value
            const id = idInput.value.trim()
            return `${apiBase}/${resource}${id ? `/${encodeURIComponent(id)}` : ''}`
        }

        function refreshEndpoint() {
            endpointLabel.textContent = currentEndpoint()
        }

        [methodInput, resourceInput, idInput].forEach((control) => {
            control.addEventListener('input', refreshEndpoint);
            control.addEventListener('change', refreshEndpoint);
        });
        refreshEndpoint();

        requestForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const method = methodInput.value
            const id = idInput.value.trim()
            if ((method === 'PATCH' || method === 'DELETE') && !id) {
                statusLabel.textContent = 'Record ID required'
                responseOutput.textContent = `${method} requires a record ID.`
                return
            }

            const options = { method, headers: { Accept: 'application/json' } }
            if (method === 'POST' || method === 'PATCH') {
                try {
                    options.headers['Content-Type'] = 'application/json'
                    options.body = JSON.stringify(JSON.parse(payloadInput.value))
                } catch {
                    statusLabel.textContent = 'Invalid JSON'
                    responseOutput.textContent = 'Check the request body and try again.'
                    return
                }
            }

            sendButton.disabled = true
            statusLabel.textContent = 'Sending…'
            responseOutput.textContent = ''
            refreshEndpoint()
            try {
                const response = await fetch(currentEndpoint(), options)
                const text = await response.text()
                let body = text
                try { body = JSON.stringify(JSON.parse(text), null, 2) } catch {}
                statusLabel.textContent = `${response.status} ${response.statusText}`
                responseOutput.textContent = body || '(empty response)'
            } catch (error) {
                statusLabel.textContent = 'Request failed'
                responseOutput.textContent = error instanceof Error ? error.message : 'Could not send request.'
            } finally {
                sendButton.disabled = false
            }
        });
    </script>
</body>
</html>

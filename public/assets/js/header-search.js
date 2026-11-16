/*
 * Header search: as the user types, show up to 5 anime whose title matches, plus a
 * "Show more results" button that opens the full anime list filtered by the same text.
 *
 * Needs on the <form class="header-search">:
 *   data-suggest-url  the API list endpoint, e.g. /api.php/anime
 *   data-results-url  the page for all results, e.g. /anime
 *   data-detail-url   the base of a detail page, e.g. /anime  (-> /anime/12)
 */
(function () {
    const form = document.querySelector(".header-search[data-suggest-url]");
    if (!form) return;

    const input = form.querySelector("input[name='q']");
    const suggestUrl = form.dataset.suggestUrl;
    const resultsUrl = form.dataset.resultsUrl;
    const detailUrl = form.dataset.detailUrl;

    const SUGGESTION_COUNT = 5;          // the API allows 5, 10 or 20
    const SORT = "popularity_asc";       // most popular matches first
    const DELAY_MS = 200;                // wait for a short pause in typing

    // ---- Build the dropdown (ARIA combobox + listbox) ----
    const panel = document.createElement("div");
    panel.className = "search-suggest";
    panel.id = "header-search-suggest";
    panel.hidden = true;

    const listbox = document.createElement("ul");
    listbox.className = "search-suggest-list";
    listbox.id = "header-search-listbox";
    listbox.setAttribute("role", "listbox");
    listbox.setAttribute("aria-label", "Anime suggestions");

    const status = document.createElement("p");
    status.className = "search-suggest-status";
    status.setAttribute("aria-live", "polite");

    const moreButton = document.createElement("button");
    moreButton.type = "submit";
    moreButton.className = "search-suggest-more";
    moreButton.id = "header-search-more";
    moreButton.textContent = "Show more results";

    panel.append(listbox, status, moreButton);
    form.appendChild(panel);

    input.setAttribute("role", "combobox");
    input.setAttribute("aria-autocomplete", "list");
    input.setAttribute("aria-controls", listbox.id);
    input.setAttribute("aria-expanded", "false");
    input.setAttribute("autocomplete", "off");

    let timer = null;
    let request = null;      // AbortController of the request in flight
    let lastQuery = null;    // text the dropdown currently shows results for
    let activeIndex = -1;    // highlighted option (keyboard), -1 = none

    // ---- Helpers ----
    function options() {
        return Array.from(listbox.querySelectorAll("[role='option']"));
    }

    function open() {
        panel.hidden = false;
        input.setAttribute("aria-expanded", "true");
    }

    function close() {
        panel.hidden = true;
        input.setAttribute("aria-expanded", "false");
        setActive(-1);
    }

    function setActive(index) {
        const items = options();
        activeIndex = index;
        items.forEach((item, i) => item.setAttribute("aria-selected", i === index ? "true" : "false"));
        moreButton.classList.toggle("is-active", index === items.length);
        if (index >= 0 && index < items.length) {
            input.setAttribute("aria-activedescendant", items[index].id);
            items[index].scrollIntoView({ block: "nearest" });
        } else {
            input.removeAttribute("aria-activedescendant");
        }
    }

    function allResultsUrl(query) {
        const params = new URLSearchParams({ q: query, sort: SORT });
        return `${resultsUrl}?${params.toString()}`;
    }

    function setStatus(text, loading = false) {
        status.textContent = text;
        status.hidden = text === "";
        status.classList.toggle("is-loading", loading);
        panel.setAttribute("aria-busy", loading ? "true" : "false");
    }

    // Small "Loading results…" dropdown while waiting for the API
    function showLoading() {
        listbox.replaceChildren();
        listbox.hidden = true;
        moreButton.hidden = true;
        setStatus("Loading results…", true);
        open();
    }

    // ---- Render ----
    function render(records, query) {
        listbox.replaceChildren();

        records.forEach((record, i) => {
            const item = document.createElement("li");
            item.setAttribute("role", "option");
            item.id = `header-search-option-${i}`;
            item.setAttribute("aria-selected", "false");

            const link = document.createElement("a");
            link.className = "search-suggest-item";
            link.href = `${detailUrl}/${encodeURIComponent(record.id)}`;
            link.tabIndex = -1;

            const thumb = document.createElement("span");
            thumb.className = "search-suggest-thumb";
            if (record.image) {
                const img = document.createElement("img");
                img.src = record.image;
                img.alt = "";
                img.loading = "lazy";
                thumb.appendChild(img);
            }

            const text = document.createElement("span");
            text.className = "search-suggest-text";
            const title = document.createElement("span");
            title.className = "search-suggest-title";
            title.textContent = record.title || "Untitled";
            const meta = document.createElement("span");
            meta.className = "search-suggest-meta";
            const showType = record.show_type;
            const episodeCount = record.episode_count;
            meta.textContent = [
                showType,
                episodeCount ? `${episodeCount} episode${Number(episodeCount) === 1 ? "" : "s"}` : null,
                record.status,
            ].filter(Boolean).join(" · ");
            text.append(title, meta);

            link.append(thumb, text);
            item.appendChild(link);
            listbox.appendChild(item);
        });

        listbox.hidden = records.length === 0;
        setStatus(records.length === 0 ? `No anime found for “${query}”.` : "", false);
        moreButton.hidden = records.length === 0;
        setActive(-1);
        open();
    }

    // ---- Fetch suggestions ----
    function search(query) {
        if (request) request.abort();
        request = new AbortController();
        showLoading();

        const params = new URLSearchParams({ q: query, limit: String(SUGGESTION_COUNT), sort: SORT });
        fetch(`${suggestUrl}?${params.toString()}`, {
            headers: { "Accept": "application/json" },
            signal: request.signal,
        })
            .then(async response => {
                const result = await response.json();
                if (!response.ok || !Array.isArray(result)) {
                    throw new Error(result && result.error ? result.error : "Search failed.");
                }
                return result;
            })
            .then(records => {
                if (input.value.trim() !== query) return; // the user kept typing
                lastQuery = query;
                render(records.slice(0, SUGGESTION_COUNT), query);
            })
            .catch(error => {
                if (error.name === "AbortError") return;
                listbox.replaceChildren();
                listbox.hidden = true;
                moreButton.hidden = true;
                setStatus("Couldn't load suggestions.", false);
                open();
            });
    }

    // ---- Events ----
    input.addEventListener("input", () => {
        clearTimeout(timer);
        const query = input.value.trim();
        if (query === "") {
            if (request) request.abort();
            lastQuery = null;
            close();
            return;
        }
        if (query === lastQuery && !panel.hidden) return; // e.g. only a space was added
        lastQuery = null;
        showLoading(); // show it straight away, not only after the typing pause
        timer = setTimeout(() => search(query), DELAY_MS);
    });

    input.addEventListener("focus", () => {
        if (input.value.trim() !== "" && input.value.trim() === lastQuery) open();
    });

    input.addEventListener("keydown", event => {
        const count = options().length;
        const last = moreButton.hidden ? count - 1 : count; // the button is the last stop

        if (event.key === "ArrowDown") {
            event.preventDefault();
            if (panel.hidden) {
                if (input.value.trim() !== "") search(input.value.trim());
                return;
            }
            setActive(activeIndex >= last ? -1 : activeIndex + 1);
        } else if (event.key === "ArrowUp") {
            if (panel.hidden) return;
            event.preventDefault();
            setActive(activeIndex <= -1 ? last : activeIndex - 1);
        } else if (event.key === "Escape") {
            if (!panel.hidden) {
                event.preventDefault();
                close();
            }
        } else if (event.key === "Enter" && !panel.hidden && activeIndex >= 0 && activeIndex < count) {
            // Enter on a highlighted anime opens it; otherwise the form submits (all results)
            event.preventDefault();
            window.location.href = options()[activeIndex].querySelector("a").href;
        }
    });

    // Enter / "Show more results": every anime matching the text
    form.addEventListener("submit", event => {
        const query = input.value.trim();
        event.preventDefault();
        window.location.href = query === "" ? resultsUrl : allResultsUrl(query);
    });

    // Click outside closes the dropdown
    document.addEventListener("pointerdown", event => {
        if (!form.contains(event.target)) close();
    });
    form.addEventListener("focusout", event => {
        if (event.relatedTarget && !form.contains(event.relatedTarget)) close();
    });
})();

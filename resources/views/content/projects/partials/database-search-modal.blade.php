{{--
    Search databases modal (IEEE Xplore, Scopus, OpenAlex, PubMed), button target #databaseSearchModal.
    Runs a search string linked to an API database and imports the results the user selects.
    Needs $project; refreshes window.studiesTable after an import.
--}}
<div class="modal fade" id="databaseSearchModal" tabindex="-1" aria-labelledby="databaseSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="width: 95vw; max-width: 95vw; height: 95vh; margin: 2.5vh auto;">
        <div class="modal-content" style="height: 95vh; max-height: 95vh;">
            <div class="modal-header">
                <h5 class="modal-title" id="databaseSearchModalLabel">
                    <i class="icon-base ri ri-search-eye-line me-2"></i>Search databases
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="dbSearchSource" class="form-label">Database</label>
                        <select id="dbSearchSource" class="form-select">
                            @foreach(\App\Models\SearchString::API_SOURCES as $apiKey => $apiName)
                            <option value="{{ $apiKey }}">{{ $apiName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label for="dbSearchString" class="form-label">Search string</label>
                        <select id="dbSearchString" class="form-select"></select>
                        <div class="alert alert-warning small mt-2 mb-0 d-none" id="dbSearchNoString">
                            No search string of this project is linked to this database. On the
                            <a href="{{ route('projects.show', $project) }}">project page</a>, add or edit a search string and choose it in <strong>API database</strong>.
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="dbSearchQuery" class="form-label">Query sent to the API</label>
                        <textarea id="dbSearchQuery" class="form-control" rows="3" style="font-family: 'Courier New', monospace; font-size: 12px;"></textarea>
                        <div class="form-text" id="dbSearchSyntax"></div>
                        <div class="form-text text-warning d-none" id="dbSearchEdited">
                            <i class="icon-base ri ri-error-warning-line me-1"></i>The query differs from the saved search string. Imported studies are still linked to that search string; update it on the project page to keep the protocol in step.
                        </div>
                    </div>

                    <div class="col-12" id="dbSearchKeys"></div>

                    <div class="col-12 d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-primary" id="dbSearchRunBtn">
                            <i class="icon-base ri ri-search-line me-1"></i>Run search
                        </button>
                        <span class="text-muted small" id="dbSearchStatus"></span>
                    </div>
                </div>

                <div id="dbSearchResults" class="mt-4 d-none">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h6 class="mb-0 me-2" id="dbSearchSummary"></h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="dbSearchMoreBtn">
                            <i class="icon-base ri ri-arrow-down-line me-1"></i>Load more
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="dbSearchAllBtn">
                            <i class="icon-base ri ri-download-2-line me-1"></i><span>Load all</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger d-none" id="dbSearchStopBtn">
                            <i class="icon-base ri ri-stop-line me-1"></i>Stop
                        </button>
                        <div class="ms-md-auto d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="dbSearchSelectAllBtn">Select all loaded</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="dbSearchSelectNewBtn">Select all not in project</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="dbSearchClearBtn">Clear selection</button>
                            <div class="form-check form-switch mb-0 ms-1">
                                <input class="form-check-input" type="checkbox" id="dbSearchHideExisting">
                                <label class="form-check-label small" for="dbSearchHideExisting">Hide studies already in the project</label>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive border rounded" style="max-height: max(320px, calc(95vh - 470px));">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th style="width: 32px;"><input class="form-check-input" type="checkbox" id="dbSearchCheckPage" title="Select all shown"></th>
                                    <th style="width: 40px;">#</th>
                                    <th>Title</th>
                                    <th style="width: 18%;">Authors</th>
                                    <th style="width: 60px;">Year</th>
                                    <th style="width: 16%;">Venue</th>
                                    <th style="width: 110px;">Type</th>
                                    <th style="width: 160px;">DOI</th>
                                </tr>
                            </thead>
                            <tbody id="dbSearchItems"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto small text-muted" id="dbSearchImportStatus"></span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="dbSearchImportBtn" disabled>
                    <i class="icon-base ri ri-add-line me-1"></i><span>Add selected (0)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('databaseSearchModal');
    if (!modalEl) return;

    const projectId = {{ $project->id }};
    const LOAD_ALL_CAP = 2000;
    const IMPORT_BATCH = 200;
    const headers = {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
    };
    const el = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const notify = (message, type) => typeof window.showToast === 'function' ? window.showToast(message, type) : alert(message);

    const keyFields = {
        ieee: [{field: 'ieee_api_key', label: 'IEEE Xplore API key', required: true,
                help: 'Free key from <a href="https://developer.ieee.org/" target="_blank" rel="noopener">developer.ieee.org</a> (daily call limit).'}],
        scopus: [{field: 'scopus_api_key', label: 'Scopus API key', required: true,
                  help: 'Key from <a href="https://dev.elsevier.com/" target="_blank" rel="noopener">dev.elsevier.com</a>; works from the institution network or with an institutional token.'},
                 {field: 'scopus_insttoken', label: 'Institutional token (optional)', required: false,
                  help: 'Asked from Elsevier by the library, for use outside the institution network.'}],
        openalex: [],
        pubmed: [{field: 'pubmed_api_key', label: 'NCBI API key (optional)', required: false,
                  help: 'Raises the rate limit; from your <a href="https://account.ncbi.nlm.nih.gov/settings/" target="_blank" rel="noopener">NCBI account settings</a>.'}]
    };
    const syntaxHelp = {
        ieee: 'IEEE Xplore command search: AND, OR, NOT, quotes, parentheses and fields such as "Abstract":term or "Document Title":term.',
        scopus: 'Scopus advanced search: TITLE-ABS-KEY(...), AND, OR, AND NOT, PUBYEAR > 2019, DOCTYPE(ar)...',
        openalex: 'Searched in titles and abstracts: AND, OR, NOT (uppercase), quotes and parentheses. Results include every work type (check the Type column).',
        pubmed: 'PubMed syntax: AND, OR, NOT, quotes, field tags such as [tiab], [mh], [dp].'
    };

    let config = null;
    let state = null; // {source, searchStringId, query, total, nextPage, hasMore, items: []}
    let stopRequested = false;
    let busy = false;

    function setBusy(value, message) {
        busy = value;
        el('dbSearchRunBtn').disabled = value;
        el('dbSearchStatus').innerHTML = message || '';
        renderControls();
    }

    function stringsFor(source) {
        return (config?.search_strings || []).filter(s => s.api_source === source);
    }

    function renderStrings() {
        const source = el('dbSearchSource').value;
        const strings = stringsFor(source);
        el('dbSearchString').innerHTML = strings.map(s =>
            '<option value="' + s.id + '">' + esc(s.database) + ' — ' + esc(s.string.length > 90 ? s.string.slice(0, 90) + '…' : s.string) + '</option>'
        ).join('');
        el('dbSearchString').disabled = strings.length === 0;
        el('dbSearchNoString').classList.toggle('d-none', strings.length > 0);
        el('dbSearchSyntax').textContent = syntaxHelp[source] || '';
        fillQuery();
        renderKeys();
    }

    function currentString() {
        const id = parseInt(el('dbSearchString').value, 10);
        return (config?.search_strings || []).find(s => s.id === id) || null;
    }

    function fillQuery() {
        const s = currentString();
        el('dbSearchQuery').value = s ? s.string : '';
        el('dbSearchEdited').classList.add('d-none');
        el('dbSearchRunBtn').disabled = !s;
    }

    function renderKeys() {
        const source = el('dbSearchSource').value;
        const fields = keyFields[source] || [];
        if (!fields.length) {
            el('dbSearchKeys').innerHTML = '<div class="small text-muted"><i class="icon-base ri ri-information-line me-1"></i>OpenAlex needs no key.</div>';
            return;
        }
        el('dbSearchKeys').innerHTML = '<div class="row g-3">' + fields.map(f => {
            const saved = !!config?.saved_keys?.[f.field];
            return '<div class="col-md-6">' +
                '<label class="form-label" for="dbKey_' + f.field + '">' + esc(f.label) + (f.required ? ' <span class="text-danger">*</span>' : '') + '</label>' +
                '<input type="password" class="form-control" id="dbKey_' + f.field + '" data-key-field="' + f.field + '" autocomplete="off" maxlength="255" placeholder="' + (saved ? 'Saved; leave empty to keep it' : 'Paste the key') + '">' +
                '<div class="form-text">' + f.help + ' Saved encrypted to your account.' +
                (saved ? ' <span class="text-success">A key is saved.</span>' : '') + '</div>' +
                (saved ? '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="dbKeyClear_' + f.field + '" data-clear-field="' + f.field + '">' +
                    '<label class="form-check-label small" for="dbKeyClear_' + f.field + '">Remove the saved key</label></div>' : '') +
                '</div>';
        }).join('') + '</div>';
    }

    function collectKeys() {
        const keys = {}, clear = [];
        el('dbSearchKeys').querySelectorAll('[data-key-field]').forEach(input => {
            if (input.value.trim()) keys[input.dataset.keyField] = input.value.trim();
        });
        el('dbSearchKeys').querySelectorAll('[data-clear-field]').forEach(box => {
            if (box.checked) clear.push(box.dataset.clearField);
        });
        return {keys, clear};
    }

    function loadConfig() {
        return fetch('/projects/' + projectId + '/database-search/config', { headers })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => { config = data; });
    }

    function fetchPage(page, withKeys) {
        const body = {
            source: state.source,
            search_string_id: state.searchStringId,
            query: state.query,
            page: page
        };
        if (withKeys) {
            const {keys, clear} = collectKeys();
            body.keys = keys;
            body.clear_keys = clear;
        }
        return fetch('/projects/' + projectId + '/database-search/search', {
            method: 'POST',
            headers: Object.assign({'Content-Type': 'application/json'}, headers),
            body: JSON.stringify(body)
        }).then(r => r.json().then(data => {
            if (!r.ok || !data.success) {
                const message = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Search failed (HTTP ' + r.status + ').');
                throw new Error(message);
            }
            return data;
        }));
    }

    function addPage(data) {
        const offset = state.items.length;
        data.items.forEach((item, i) => {
            item._n = offset + i + 1;
            item._selected = false;
            state.items.push(item);
        });
        state.total = data.total;
        state.hasMore = data.has_more;
        state.nextPage = data.page + 1;
    }

    function run() {
        const s = currentString();
        if (!s || busy) return;
        state = {
            source: el('dbSearchSource').value,
            searchStringId: s.id,
            query: el('dbSearchQuery').value.trim(),
            total: 0, nextPage: 1, hasMore: false, items: []
        };
        setBusy(true, '<i class="icon-base ri ri-loader-4-line ri-spin me-1"></i>Searching...');
        fetchPage(1, true)
            .then(data => {
                addPage(data);
                // Keys typed were saved by the server; show them as saved
                return loadConfig().then(renderKeys);
            })
            .then(() => setBusy(false, ''))
            .catch(err => { state = null; setBusy(false, '<span class="text-danger">' + esc(err.message) + '</span>'); })
            .finally(render);
    }

    function loadMore() {
        if (!state || !state.hasMore || busy) return Promise.resolve();
        setBusy(true, '<i class="icon-base ri ri-loader-4-line ri-spin me-1"></i>Loading page ' + state.nextPage + '...');
        return fetchPage(state.nextPage, false)
            .then(data => { addPage(data); setBusy(false, ''); })
            .catch(err => { setBusy(false, '<span class="text-danger">' + esc(err.message) + '</span>'); throw err; })
            .finally(render);
    }

    async function loadAll() {
        if (!state || busy) return;
        stopRequested = false;
        el('dbSearchStopBtn').classList.remove('d-none');
        const target = Math.min(state.total, LOAD_ALL_CAP, config.max_results[state.source]);
        try {
            while (state.hasMore && state.items.length < target && !stopRequested) {
                await loadMore();
                el('dbSearchStatus').innerHTML = '<i class="icon-base ri ri-loader-4-line ri-spin me-1"></i>Loaded ' + state.items.length + ' of ' + target + '...';
            }
            el('dbSearchStatus').innerHTML = stopRequested ? 'Stopped.' :
                (state.items.length < state.total ? 'Loaded the first ' + state.items.length + ' results (limit ' + target + '); narrow the string to see the rest.' : '');
        } catch (e) { /* message already shown */ }
        el('dbSearchStopBtn').classList.add('d-none');
        renderControls();
    }

    function visibleItems() {
        if (!state) return [];
        const hide = el('dbSearchHideExisting').checked;
        return state.items.filter(i => !(hide && i.in_project));
    }

    function render() {
        el('dbSearchResults').classList.toggle('d-none', !state);
        if (!state) { renderControls(); return; }

        const items = visibleItems();
        el('dbSearchItems').innerHTML = items.length ? items.map(i => {
            const doi = i.doi ? '<a href="https://doi.org/' + esc(i.doi) + '" target="_blank" rel="noopener" class="text-break small">' + esc(i.doi) + '</a>' : '';
            const title = i.link ? '<a href="' + esc(i.link) + '" target="_blank" rel="noopener">' + esc(i.name) + '</a>' : esc(i.name);
            return '<tr class="' + (i._selected ? 'table-active' : '') + '" data-n="' + i._n + '">' +
                '<td><input class="form-check-input" type="checkbox" data-select="' + i._n + '"' + (i._selected ? ' checked' : '') + '></td>' +
                '<td class="small text-muted">' + i._n + '</td>' +
                '<td>' + title +
                    (i.in_project ? ' <span class="badge bg-label-warning ms-1">Already in project</span>' : '') +
                    (i.abstract ? '<details class="small text-muted mt-1"><summary>Abstract</summary>' + esc(i.abstract) + '</details>' : '') +
                    (i.keywords ? '<div class="small text-muted mt-1"><i class="icon-base ri ri-price-tag-3-line me-1"></i>' + esc(i.keywords.length > 160 ? i.keywords.slice(0, 160) + '…' : i.keywords) + '</div>' : '') +
                '</td>' +
                '<td class="small"><div style="max-height: 4.5em; overflow: hidden;" title="' + esc(i.authors) + '">' + esc(i.authors) + '</div></td>' +
                '<td class="small">' + esc(i.year) + '</td>' +
                '<td class="small">' + esc(i.journal_conference) + '</td>' +
                '<td class="small">' + esc(i.document_type) + '</td>' +
                '<td>' + doi + '</td></tr>';
        }).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No results to show.</td></tr>';
        renderControls();
    }

    function renderControls() {
        const selected = state ? state.items.filter(i => i._selected).length : 0;
        if (state) {
            const inProject = state.items.filter(i => i.in_project).length;
            el('dbSearchSummary').textContent = state.total.toLocaleString() + ' results · ' + state.items.length.toLocaleString() + ' loaded' +
                (inProject ? ' · ' + inProject + ' already in project' : '') + ' · ' + selected + ' selected';
            const target = Math.min(state.total, LOAD_ALL_CAP, config?.max_results?.[state.source] || LOAD_ALL_CAP);
            el('dbSearchMoreBtn').disabled = busy || !state.hasMore;
            el('dbSearchAllBtn').disabled = busy || !state.hasMore || state.items.length >= target;
            el('dbSearchAllBtn').querySelector('span').textContent = 'Load all (' + target.toLocaleString() + (state.total > target ? ' max' : '') + ')';
            const shown = visibleItems();
            const box = el('dbSearchCheckPage');
            box.checked = shown.length > 0 && shown.every(i => i._selected);
            box.indeterminate = !box.checked && shown.some(i => i._selected);
        }
        el('dbSearchImportBtn').disabled = busy || selected === 0;
        el('dbSearchImportBtn').querySelector('span').textContent = 'Add selected (' + selected + ')';
    }

    function setSelected(predicate, value) {
        if (!state) return;
        state.items.forEach(i => { if (predicate(i)) i._selected = value; });
        render();
    }

    async function importSelected() {
        if (!state || busy) return;
        const selected = state.items.filter(i => i._selected);
        if (!selected.length) return;
        setBusy(true, '');
        const fields = ['name', 'authors', 'year', 'doi', 'keywords', 'abstract', 'link', 'journal_conference', 'document_type'];
        let imported = 0;
        try {
            for (let start = 0; start < selected.length; start += IMPORT_BATCH) {
                const batch = selected.slice(start, start + IMPORT_BATCH);
                el('dbSearchImportStatus').innerHTML = '<i class="icon-base ri ri-loader-4-line ri-spin me-1"></i>Adding ' + Math.min(start + batch.length, selected.length) + ' of ' + selected.length + '...';
                const response = await fetch('/projects/' + projectId + '/database-search/import', {
                    method: 'POST',
                    headers: Object.assign({'Content-Type': 'application/json'}, headers),
                    body: JSON.stringify({
                        source: state.source,
                        search_string_id: state.searchStringId,
                        items: batch.map(i => Object.fromEntries(fields.map(f => [f, i[f] ?? null])))
                    })
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'HTTP ' + response.status));
                }
                imported += data.imported;
                batch.forEach(i => { i._selected = false; i.in_project = true; });
            }
            el('dbSearchImportStatus').innerHTML = '<span class="text-success">' + imported + ' studies added.</span>';
            notify(imported + ' studies added to the project.', 'success');
        } catch (err) {
            el('dbSearchImportStatus').innerHTML = '<span class="text-danger">' + esc(err.message) + (imported ? ' (' + imported + ' added before the error)' : '') + '</span>';
            notify('Error adding studies: ' + err.message, 'error');
        }
        setBusy(false, '');
        render();
        if (imported && window.studiesTable) window.studiesTable.ajax.reload(null, false);
    }

    // ------------------------------------------------------------ events
    modalEl.addEventListener('show.bs.modal', function() {
        if (config) return;
        el('dbSearchStatus').innerHTML = '<i class="icon-base ri ri-loader-4-line ri-spin me-1"></i>Loading...';
        loadConfig().then(() => {
            // Start on the first database that has a search string
            const first = Object.keys(config.sources).find(src => stringsFor(src).length);
            if (first) el('dbSearchSource').value = first;
            renderStrings();
            el('dbSearchStatus').innerHTML = '';
        }).catch(() => { el('dbSearchStatus').innerHTML = '<span class="text-danger">Could not load the search strings.</span>'; });
    });
    el('dbSearchSource').addEventListener('change', renderStrings);
    el('dbSearchString').addEventListener('change', fillQuery);
    el('dbSearchQuery').addEventListener('input', function() {
        const s = currentString();
        el('dbSearchEdited').classList.toggle('d-none', !s || this.value.trim() === s.string.trim());
    });
    el('dbSearchRunBtn').addEventListener('click', run);
    el('dbSearchMoreBtn').addEventListener('click', () => loadMore().catch(() => {}));
    el('dbSearchAllBtn').addEventListener('click', loadAll);
    el('dbSearchStopBtn').addEventListener('click', () => { stopRequested = true; });
    el('dbSearchSelectAllBtn').addEventListener('click', () => setSelected(() => true, true));
    el('dbSearchSelectNewBtn').addEventListener('click', () => setSelected(i => !i.in_project, true));
    el('dbSearchClearBtn').addEventListener('click', () => setSelected(() => true, false));
    el('dbSearchHideExisting').addEventListener('change', render);
    el('dbSearchCheckPage').addEventListener('change', function() {
        const shown = new Set(visibleItems().map(i => i._n));
        setSelected(i => shown.has(i._n), this.checked);
    });
    el('dbSearchItems').addEventListener('change', function(e) {
        const n = e.target.dataset.select;
        if (!n || !state) return;
        const item = state.items.find(i => i._n === parseInt(n, 10));
        if (item) item._selected = e.target.checked;
        e.target.closest('tr').classList.toggle('table-active', e.target.checked);
        renderControls();
    });
    el('dbSearchImportBtn').addEventListener('click', importSelected);
});
</script>

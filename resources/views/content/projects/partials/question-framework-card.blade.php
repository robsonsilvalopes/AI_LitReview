{{--
    Research question framework card (PICO, PICOS, PICOT, PICOC, PCC, SPIDER, PEO, custom) for the
    project page, with the search string draft modal. Needs $project. The owner edits; collaborators view.
--}}
@php
    $qfCanEdit = $project->user_id === auth()->id();
    $qfDefinitions = \App\Services\QuestionFramework::definitions();
@endphp
<div class="row mt-4 project-step-anchor" id="question-framework">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-node-tree me-2"></i>Research Question Framework
                </h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <select id="qfFramework" class="form-select form-select-sm" style="width: auto;" @disabled(!$qfCanEdit) aria-label="Framework">
                        <option value="">None</option>
                        @foreach($qfDefinitions as $key => $def)
                        <option value="{{ $key }}" @selected($project->question_framework === $key)>{{ $def['name'] }} — {{ $def['use'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="qfDraftBtn">
                        <i class="icon-base ri ri-search-eye-line me-1"></i>Generate search string draft
                    </button>
                    @if($qfCanEdit)
                    <button type="button" class="btn btn-sm btn-primary" id="qfSaveBtn">
                        <i class="icon-base ri ri-save-line me-1"></i>Save
                    </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3" id="qfHelp">
                    Structure the review question with a framework. Give each element a description and its keywords and synonyms
                    (one per line); the elements marked <strong>Use in search</strong> become blocks of the search string draft
                    (keywords joined with OR, blocks joined with AND). A line in parentheses, such as
                    <code>((software OR app*) AND (test* OR verification))</code>, is kept as a boolean expression. The framework is available to prompt templates as <code>[[pico]]</code>
                    and fills items 4 and 5 of the PRISMA checklist.
                </p>
                <div id="qfElements"></div>
                @if($qfCanEdit)
                <button type="button" class="btn btn-sm btn-outline-secondary d-none mt-2" id="qfAddElementBtn">
                    <i class="icon-base ri ri-add-line me-1"></i>Add element
                </button>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Search string draft Modal -->
<div class="modal fade" id="qfDraftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="icon-base ri ri-search-eye-line me-2"></i>Search string draft</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="qfDraftBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('qfElements');
    if (!container) return;

    const projectId = {{ $project->id }};
    const canEdit = @json($qfCanEdit);
    const definitions = @json($qfDefinitions);
    let elements = @json($project->question_framework_elements ?? []);
    const el = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const notify = (message, type) => typeof window.showToast === 'function' ? window.showToast(message, type) : alert(message);
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    const syntaxApiSource = {scopus: 'scopus', ieee: 'ieee', pubmed: 'pubmed', openalex: 'openalex'};
    let dirty = false;

    // Rows for the chosen framework, keeping what was typed for elements with the same key
    function elementsFor(framework) {
        if (!framework) return [];
        if (framework === 'custom') {
            return elements.length ? elements : [{key: '', label: '', description: '', keywords: [], not_applicable: false, search: true}];
        }
        const byKey = Object.fromEntries(elements.map(e => [e.key, e]));
        return definitions[framework].elements.map(d => Object.assign(
            {key: d.key, label: d.label, description: '', keywords: [], not_applicable: false, search: d.search},
            byKey[d.key] ? {description: byKey[d.key].description, keywords: byKey[d.key].keywords,
                            not_applicable: byKey[d.key].not_applicable, search: byKey[d.key].search} : {},
            {key: d.key, label: d.label}
        ));
    }

    function render() {
        const framework = el('qfFramework').value;
        const custom = framework === 'custom';
        el('qfAddElementBtn')?.classList.toggle('d-none', !custom || !canEdit);
        if (!framework) {
            container.innerHTML = '<div class="text-muted small">No framework selected.</div>';
            return;
        }
        const dis = canEdit ? '' : ' disabled';
        container.innerHTML = elements.map((e, i) => `
            <div class="border rounded p-3 mb-2 qf-element${e.not_applicable ? ' bg-label-secondary' : ''}" data-index="${i}">
                <div class="row g-2 align-items-start">
                    <div class="col-md-2">
                        ${custom
                            ? `<input type="text" class="form-control form-control-sm mb-1" data-f="key" value="${esc(e.key)}" maxlength="10" placeholder="Key (e.g. P)"${dis}>
                               <input type="text" class="form-control form-control-sm" data-f="label" value="${esc(e.label)}" maxlength="100" placeholder="Element name"${dis}>`
                            : `<div class="fw-semibold"><span class="badge bg-primary me-1">${esc(e.key)}</span>${esc(e.label)}</div>`}
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="qfNa${i}" data-f="not_applicable"${e.not_applicable ? ' checked' : ''}${dis}>
                            <label class="form-check-label small" for="qfNa${i}">Not applicable</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="qfSearch${i}" data-f="search"${e.search ? ' checked' : ''}${dis}>
                            <label class="form-check-label small" for="qfSearch${i}">Use in search</label>
                        </div>
                        ${custom && canEdit ? `<button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 mt-1" style="font-size: .75rem;" data-remove="${i}">Remove</button>` : ''}
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Description</label>
                        <textarea class="form-control form-control-sm" rows="3" data-f="description" maxlength="5000"
                                  placeholder="What this element means in the review"${dis}>${esc(e.description)}</textarea>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Keywords and synonyms <span class="text-muted">(one per line)</span></label>
                        <textarea class="form-control form-control-sm" rows="3" data-f="keywords"
                                  placeholder="e.g.&#10;software testing&#10;test automation&#10;automated testing"${dis}>${esc((e.keywords || []).join('\n'))}</textarea>
                    </div>
                </div>
            </div>`).join('');
    }

    function collect() {
        container.querySelectorAll('.qf-element').forEach(row => {
            const e = elements[parseInt(row.dataset.index, 10)];
            row.querySelectorAll('[data-f]').forEach(input => {
                const f = input.dataset.f;
                if (input.type === 'checkbox') e[f] = input.checked;
                else if (f === 'keywords') e[f] = input.value.split(/[\r\n;]+/).map(k => k.trim()).filter(Boolean);
                else e[f] = input.value;
            });
        });
    }

    el('qfFramework').addEventListener('change', function() {
        collect();
        elements = elementsFor(this.value);
        dirty = true;
        render();
    });
    container.addEventListener('input', () => { dirty = true; });
    container.addEventListener('change', function(e) {
        if (e.target.dataset.f === 'not_applicable') {
            e.target.closest('.qf-element').classList.toggle('bg-label-secondary', e.target.checked);
        }
    });
    container.addEventListener('click', function(e) {
        const index = e.target.closest('[data-remove]')?.dataset.remove;
        if (index === undefined) return;
        collect();
        elements.splice(parseInt(index, 10), 1);
        dirty = true;
        render();
    });
    el('qfAddElementBtn')?.addEventListener('click', function() {
        collect();
        elements.push({key: '', label: '', description: '', keywords: [], not_applicable: false, search: true});
        dirty = true;
        render();
    });

    function save() {
        collect();
        return fetch('/projects/' + projectId + '/question-framework', {
            method: 'POST', headers,
            body: JSON.stringify({framework: el('qfFramework').value || null, elements})
        }).then(r => r.json().then(data => {
            if (!r.ok || !data.success) {
                throw new Error(data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'HTTP ' + r.status));
            }
            elements = data.elements || [];
            dirty = false;
            render();
            return data;
        }));
    }
    el('qfSaveBtn')?.addEventListener('click', function() {
        this.disabled = true;
        save().then(data => notify(data.message, 'success'))
            .catch(err => notify('Error: ' + err.message, 'error'))
            .finally(() => { this.disabled = false; });
    });
    window.addEventListener('beforeunload', e => { if (dirty && canEdit) { e.preventDefault(); e.returnValue = ''; } });

    // ------------------------------------------------------------ Search string draft
    el('qfDraftBtn').addEventListener('click', function() {
        const show = () => fetch('/projects/' + projectId + '/question-framework/drafts', {headers})
            .then(r => r.json())
            .then(data => {
                const drafts = data.drafts || {};
                let html = '';
                if (!Object.keys(drafts).length) {
                    html = '<div class="alert alert-warning mb-0">No element marked <strong>Use in search</strong> has keywords. Add keywords to the elements and save.</div>';
                } else {
                    html += '<p class="small text-muted">Built from: <strong>' + esc(data.used.join(', ')) + '</strong>' +
                        (data.skipped.length ? ' · skipped (no keywords): ' + esc(data.skipped.join(', ')) : '') +
                        '. A draft is a starting point: check field codes, truncation (e.g. <code>learn*</code>) and limits in each database before registering it.</p>';
                    for (const [syntax, draft] of Object.entries(drafts)) {
                        html += '<div class="mb-3"><div class="d-flex justify-content-between align-items-center mb-1">' +
                            '<strong>' + esc(data.syntaxes[syntax]) + '</strong><div class="d-flex gap-1">' +
                            '<button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: .75rem;" data-copy-draft="' + syntax + '"><i class="icon-base ri ri-file-copy-line me-1"></i>Copy</button>' +
                            (canEdit ? '<button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: .75rem;" data-use-draft="' + syntax + '"><i class="icon-base ri ri-add-line me-1"></i>Use as new search string</button>' : '') +
                            '</div></div><pre class="mb-0 p-2 rounded" style="white-space: pre-wrap; font-size: 12px; background: var(--bs-light-bg-subtle, #f5f5f5);">' + esc(draft) + '</pre></div>';
                    }
                }
                el('qfDraftBody').innerHTML = html;
                el('qfDraftBody').querySelectorAll('[data-copy-draft]').forEach(b => b.addEventListener('click', () =>
                    navigator.clipboard.writeText(drafts[b.dataset.copyDraft]).then(() => notify('Copied.', 'success'))));
                el('qfDraftBody').querySelectorAll('[data-use-draft]').forEach(b => b.addEventListener('click', () => {
                    const syntax = b.dataset.useDraft;
                    const draftModalEl = el('qfDraftModal');
                    draftModalEl.addEventListener('hidden.bs.modal', function openCreate() {
                        document.getElementById('searchStringForm')?.reset();
                        document.getElementById('search_string_id').value = '';
                        document.getElementById('searchStringModalTitle').textContent = 'Create Search String';
                        document.getElementById('search_string_database').value = syntax === 'generic' ? '' : data.syntaxes[syntax];
                        const apiSelect = document.getElementById('search_string_api_source');
                        if (apiSelect) apiSelect.value = syntaxApiSource[syntax] || '';
                        document.getElementById('search_string_string').value = drafts[syntax];
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('createSearchStringModal')).show();
                    }, {once: true});
                    bootstrap.Modal.getInstance(draftModalEl).hide();
                }));
                bootstrap.Modal.getOrCreateInstance(el('qfDraftModal')).show();
            })
            .catch(() => notify('Could not build the draft.', 'error'));

        // Unsaved changes are saved first, so the draft matches what is on screen
        if (dirty && canEdit) {
            save().then(show).catch(err => notify('Error saving before the draft: ' + err.message, 'error'));
        } else {
            show();
        }
    });

    render();
});
</script>

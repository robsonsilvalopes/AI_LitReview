{{--
    Screening Model Output Modal
    Usage: window.showScreeningOutput({ name, type, ai_model, date, output_ai })
    Renders the raw model response (OpenRouter / Ollama envelope or plain text) in a
    human-readable view, with the raw JSON available in a second tab.
--}}
<style>
    #screeningOutputModal .so-section + .so-section { margin-top: 1.25rem; }
    #screeningOutputModal .so-label {
        font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.04em; color: var(--bs-secondary-color); margin-bottom: 0.35rem;
    }
    #screeningOutputModal .so-text { white-space: pre-wrap; word-break: break-word; margin-bottom: 0; line-height: 1.6; }
    #screeningOutputModal .so-nested { border-left: 3px solid var(--bs-border-color); padding-left: 0.85rem; }
    #screeningOutputModal .so-kv th { width: 35%; font-weight: 500; white-space: normal; }
    #screeningOutputModal .so-kv td, #screeningOutputModal .so-kv th { vertical-align: top; }
    #screeningOutputModal .so-item { border: 1px solid var(--bs-border-color); border-radius: 0.375rem; padding: 0.75rem 0.9rem; }
    #screeningOutputModal .so-item + .so-item { margin-top: 0.6rem; }
    #screeningOutputModal details summary { cursor: pointer; }
    #screeningOutputModal pre.so-raw {
        white-space: pre-wrap; word-break: break-word; font-size: 0.8125rem;
        font-family: 'Courier New', monospace;
    }
</style>

<div class="modal fade" id="screeningOutputModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Model Output — <span id="screening-output-name"></span></h5>
                    <small class="text-muted" id="screening-output-meta"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <ul class="nav nav-tabs mb-3" role="tablist" id="screening-output-tabs">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="screening-output-readable-tab" data-bs-toggle="tab"
                            data-bs-target="#screening-output-readable" type="button" role="tab">
                            <i class="icon-base ri ri-file-text-line me-1"></i>Readable
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="screening-output-raw-tab" data-bs-toggle="tab"
                            data-bs-target="#screening-output-raw" type="button" role="tab">
                            <i class="icon-base ri ri-code-s-slash-line me-1"></i>Raw JSON
                        </button>
                    </li>
                </ul>
                <div class="tab-content p-0">
                    <div class="tab-pane fade show active" id="screening-output-readable" role="tabpanel"></div>
                    <div class="tab-pane fade" id="screening-output-raw" role="tabpanel">
                        <pre id="screening-output-json" class="so-raw mb-0 p-3 rounded bg-lighter"></pre>
                    </div>
                </div>
                <p id="screening-output-empty" class="text-muted text-center my-4 d-none"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="screening-output-copy" onclick="copyScreeningOutput()">
                    <i class="icon-base ri ri-file-copy-line me-1"></i>Copy raw
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // ---------- parsing ----------

    function tryParse(text) {
        try { return JSON.parse(text); } catch (e) { return undefined; }
    }

    // Pretty-print for the Raw tab: whole JSON, or an embedded JSON object keeping surrounding text
    function formatRaw(raw) {
        const output = String(raw).replace(/^\s+/, '');
        const whole = tryParse(output);
        if (whole !== undefined) return JSON.stringify(whole, null, 2);
        const m = output.match(/\{[\s\S]*\}/);
        if (m) {
            const inner = tryParse(m[0]);
            if (inner !== undefined) {
                return output.substring(0, m.index) + JSON.stringify(inner, null, 2) +
                    output.substring(m.index + m[0].length);
            }
        }
        return output;
    }

    // Unwrap the provider envelope: returns { content, reasoning, info }
    function unwrapEnvelope(raw) {
        const env = tryParse(String(raw).trim());
        if (!env || typeof env !== 'object' || Array.isArray(env)) {
            return { content: String(raw), reasoning: null, info: {}, envelope: null };
        }

        const info = {};
        let content = null, reasoning = null;

        // OpenRouter / OpenAI-compatible
        if (Array.isArray(env.choices) && env.choices[0]) {
            const msg = env.choices[0].message || {};
            content = msg.content ?? env.choices[0].text ?? null;
            reasoning = msg.reasoning || msg.reasoning_content || null;
            if (env.choices[0].finish_reason) info['Finish reason'] = env.choices[0].finish_reason;
        }
        // Ollama chat / generate
        else if (env.message && typeof env.message === 'object' && 'content' in env.message) {
            content = env.message.content;
            reasoning = env.message.thinking || null;
        } else if (typeof env.response === 'string') {
            content = env.response;
            reasoning = env.thinking || null;
        }

        if (env.model) info['Model'] = env.model;
        if (env.usage) {
            if (env.usage.prompt_tokens != null) info['Input tokens'] = env.usage.prompt_tokens;
            if (env.usage.completion_tokens != null) info['Output tokens'] = env.usage.completion_tokens;
            if (env.usage.total_tokens != null) info['Total tokens'] = env.usage.total_tokens;
            if (env.usage.cost != null) info['Cost (USD)'] = '$' + Number(env.usage.cost).toFixed(6);
        }
        if (env.prompt_eval_count != null) info['Input tokens'] = env.prompt_eval_count;
        if (env.eval_count != null) info['Output tokens'] = env.eval_count;
        if (env.total_duration != null) info['Duration'] = (env.total_duration / 1e9).toFixed(2) + ' s';

        // Not an envelope: the stored value already is the answer (e.g. {"error": ...} or the model JSON)
        if (content === null) return { content: null, reasoning: null, info: {}, envelope: env };

        return { content: String(content), reasoning, info, envelope: null };
    }

    // Split model text into { data (parsed JSON or null), before, after }
    function splitContent(text) {
        let t = text.trim();
        const whole = tryParse(t);
        if (whole !== undefined && whole !== null && typeof whole === 'object') return { data: whole, before: '', after: '' };

        const fence = t.match(/```(?:json)?\s*([\s\S]*?)```/i);
        if (fence) {
            const parsed = tryParse(fence[1].trim());
            if (parsed && typeof parsed === 'object') {
                return { data: parsed, before: t.substring(0, fence.index).trim(), after: t.substring(fence.index + fence[0].length).trim() };
            }
        }

        const start = t.indexOf('{'), end = t.lastIndexOf('}');
        if (start !== -1 && end > start) {
            const parsed = tryParse(t.substring(start, end + 1));
            if (parsed && typeof parsed === 'object') {
                return { data: parsed, before: t.substring(0, start).trim(), after: t.substring(end + 1).trim() };
            }
        }
        return { data: null, before: t, after: '' };
    }

    // ---------- rendering helpers ----------

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function humanize(key) {
        const s = String(key)
            .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
            .replace(/[_\-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        if (s.toLowerCase() === 'id') return 'ID';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    const DECISION_KEYS = ['status', 'overall', 'decision', 'final_decision', 'include'];

    function normalizeDecision(v) {
        if (typeof v === 'boolean') return v;
        if (typeof v === 'number') return v !== 0;
        if (typeof v === 'string') {
            const s = v.trim().toLowerCase();
            if (['include', 'included', 'true', '1', 'yes', 'accept', 'accepted'].includes(s)) return true;
            if (['exclude', 'excluded', 'false', '0', 'no', 'reject', 'rejected'].includes(s)) return false;
        }
        return null;
    }

    function isPrimitive(v) {
        return v === null || ['string', 'number', 'boolean'].includes(typeof v);
    }

    function primitiveNode(v) {
        if (v === null || v === undefined || v === '') return el('span', 'text-muted', '—');
        if (typeof v === 'boolean') {
            return el('span', 'badge rounded-pill ' + (v ? 'bg-label-success' : 'bg-label-danger'), v ? 'Yes' : 'No');
        }
        if (typeof v === 'number') return el('span', 'badge bg-label-primary fs-6', String(v));
        const s = String(v);
        // Short decision-like words get a badge, the rest is readable text
        const d = s.length <= 12 ? normalizeDecision(s) : null;
        if (d !== null && isNaN(Number(s))) {
            return el('span', 'badge rounded-pill ' + (d ? 'bg-label-success' : 'bg-label-danger'), s);
        }
        return el('p', 'so-text', s);
    }

    function kvTable(obj) {
        const table = el('table', 'table table-sm table-bordered so-kv mb-0');
        const tbody = el('tbody');
        Object.keys(obj).forEach(k => {
            const tr = el('tr');
            tr.appendChild(el('th', 'bg-lighter', humanize(k)));
            const td = el('td');
            td.appendChild(renderValue(obj[k], 1));
            tr.appendChild(td);
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        return table;
    }

    const TITLE_KEYS = ['id', 'criterion', 'criteria', 'code', 'name', 'title', 'question', 'field'];
    const LONG_TEXT_KEYS = ['justification', 'reason', 'reasoning', 'explanation', 'rationale', 'comment', 'evidence'];

    function objectsTable(rows) {
        const cols = [];
        rows.forEach(r => Object.keys(r).forEach(k => { if (!cols.includes(k)) cols.push(k); }));
        // Identifier first, long text last
        cols.sort((a, b) => {
            const rank = k => TITLE_KEYS.includes(k.toLowerCase()) ? 0 : LONG_TEXT_KEYS.includes(k.toLowerCase()) ? 2 : 1;
            return rank(a) - rank(b);
        });

        const responsive = el('div', 'table-responsive');
        const table = el('table', 'table table-sm table-bordered align-top mb-0');
        const thead = el('thead', 'table-light');
        const htr = el('tr');
        cols.forEach(c => htr.appendChild(el('th', 'text-nowrap', humanize(c))));
        thead.appendChild(htr);
        table.appendChild(thead);

        const tbody = el('tbody');
        rows.forEach(r => {
            const tr = el('tr');
            cols.forEach(c => {
                const v = r[c];
                const isTitle = TITLE_KEYS.includes(c.toLowerCase());
                const td = el('td', isTitle ? 'fw-semibold text-nowrap' : (typeof v === 'number' || typeof v === 'boolean') ? 'text-center' : '');
                if (isTitle && v !== null && v !== undefined) td.textContent = String(v);
                else if (typeof v === 'string') td.appendChild(el('span', 'so-text', v));
                else td.appendChild(primitiveNode(v));
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        responsive.appendChild(table);
        return responsive;
    }

    function renderValue(value, depth) {
        if (isPrimitive(value)) return primitiveNode(value);

        if (Array.isArray(value)) {
            if (value.length === 0) return el('span', 'text-muted', '—');
            if (value.every(isPrimitive)) {
                const ul = el('ul', 'mb-0 ps-3');
                value.forEach(v => { const li = el('li', 'mb-1'); li.appendChild(primitiveNode(v)); ul.appendChild(li); });
                return ul;
            }
            // Array of flat objects (e.g. per-criterion evaluations) → table
            if (value.every(v => v && typeof v === 'object' && !Array.isArray(v) && Object.values(v).every(isPrimitive))) {
                return objectsTable(value);
            }
            // Array of nested objects: one card each
            const wrap = el('div');
            value.forEach((item, i) => {
                const card = el('div', 'so-item');
                if (item && typeof item === 'object' && !Array.isArray(item)) {
                    const titleKey = ['criterion', 'criteria', 'name', 'title', 'id', 'question', 'field'].find(k => isPrimitive(item[k]) && item[k] !== null && item[k] !== '');
                    if (titleKey) card.appendChild(el('div', 'fw-semibold mb-2', String(item[titleKey])));
                    const rest = Object.assign({}, item);
                    if (titleKey) delete rest[titleKey];
                    card.appendChild(renderObject(rest, depth + 1));
                } else {
                    card.appendChild(el('div', 'small text-muted mb-1', '#' + (i + 1)));
                    card.appendChild(renderValue(item, depth + 1));
                }
                wrap.appendChild(card);
            });
            return wrap;
        }

        return renderObject(value, depth + 1);
    }

    function renderObject(obj, depth) {
        const keys = Object.keys(obj);
        if (keys.length === 0) return el('span', 'text-muted', '—');
        // Flat object with short values → compact key/value table
        const flat = keys.every(k => isPrimitive(obj[k]) && String(obj[k] ?? '').length <= 120);
        if (flat) return kvTable(obj);

        const wrap = el('div', depth > 1 ? 'so-nested' : '');
        keys.forEach(k => {
            const section = el('div', 'so-section');
            section.appendChild(el('div', 'so-label', humanize(k)));
            section.appendChild(renderValue(obj[k], depth));
            wrap.appendChild(section);
        });
        return wrap;
    }

    function decisionBanner(value, label) {
        const d = normalizeDecision(value);
        const box = el('div', 'alert d-flex align-items-center mb-0 ' +
            (d === true ? 'alert-success' : d === false ? 'alert-danger' : 'alert-secondary'));
        const icon = el('i', 'icon-base ri me-2 ri-24px ' +
            (d === true ? 'ri-checkbox-circle-line' : d === false ? 'ri-close-circle-line' : 'ri-question-line'));
        box.appendChild(icon);
        const text = el('div');
        text.appendChild(el('div', 'small text-uppercase fw-semibold', label));
        text.appendChild(el('div', 'fs-5 fw-semibold',
            d === true ? 'Include' : d === false ? 'Exclude' : String(value)));
        box.appendChild(text);
        return box;
    }

    function textSection(label, text, collapsed) {
        const section = el('div', 'so-section');
        if (collapsed) {
            const details = el('details');
            details.appendChild(el('summary', 'so-label d-inline-block', label));
            details.appendChild(el('p', 'so-text mt-2 text-body-secondary', text));
            section.appendChild(details);
        } else {
            section.appendChild(el('div', 'so-label', label));
            section.appendChild(el('p', 'so-text', text));
        }
        return section;
    }

    function infoLine(info) {
        const keys = Object.keys(info);
        if (!keys.length) return null;
        const wrap = el('div', 'so-section d-flex flex-wrap gap-2');
        keys.forEach(k => {
            const chip = el('span', 'badge bg-label-secondary fw-normal');
            chip.appendChild(el('span', 'fw-semibold', k + ': '));
            chip.appendChild(document.createTextNode(String(info[k])));
            wrap.appendChild(chip);
        });
        return wrap;
    }

    function renderReadable(raw) {
        const root = el('div');
        const { content, reasoning, info, envelope } = unwrapEnvelope(raw);

        let data = null, before = '', after = '';
        if (envelope) data = envelope;
        else ({ data, before, after } = splitContent(content));

        // Stored error: job exception {"error": "...", "trace": "..."} or provider error {"error": {"message", "code", ...}}
        if (envelope && envelope.error) {
            const err = envelope.error;
            const message = typeof err === 'string' ? err : (err.message || JSON.stringify(err));
            const alert = el('div', 'alert alert-danger mb-0');
            alert.appendChild(el('div', 'fw-semibold mb-1',
                'Processing error' + (err && err.code ? ' (code ' + err.code + ')' : '')));
            alert.appendChild(el('p', 'so-text', message));
            root.appendChild(alert);
            const providerDetail = err && err.metadata && typeof err.metadata.raw === 'string' ? err.metadata.raw : null;
            if (providerDetail) root.appendChild(textSection('Provider message', providerDetail));
            if (envelope.trace) root.appendChild(textSection('Stack trace (click to expand)', String(envelope.trace), true));
            return root;
        }

        if (data && typeof data === 'object' && !Array.isArray(data)) {
            const rest = Object.assign({}, data);
            const decisionKey = DECISION_KEYS.find(k => k in rest && isPrimitive(rest[k]));
            if (decisionKey) {
                const section = el('div', 'so-section');
                section.appendChild(decisionBanner(rest[decisionKey], 'Model decision'));
                root.appendChild(section);
                delete rest[decisionKey];
                // "status" is derived from "overall" by the jobs; avoid showing the same decision twice
                DECISION_KEYS.forEach(k => {
                    if (k in rest && normalizeDecision(rest[k]) === normalizeDecision(data[decisionKey])) delete rest[k];
                });
            }
            if (before) root.appendChild(textSection('Model commentary', before));
            Object.keys(rest).forEach(k => {
                const section = el('div', 'so-section');
                section.appendChild(el('div', 'so-label', humanize(k)));
                section.appendChild(renderValue(rest[k], 1));
                root.appendChild(section);
            });
            if (after) root.appendChild(textSection('Additional notes', after));
        } else if (data) {
            const section = el('div', 'so-section');
            section.appendChild(renderValue(data, 1));
            root.appendChild(section);
        } else if (before) {
            root.appendChild(el('div', 'alert alert-warning small py-2 mb-3',
                'The model did not return structured JSON — showing its answer as text.'));
            root.appendChild(textSection('Model answer', before));
        } else {
            root.appendChild(el('p', 'text-muted text-center my-4', 'The model returned an empty answer.'));
        }

        if (reasoning) root.appendChild(textSection('Model reasoning (click to expand)', String(reasoning), true));
        const meta = infoLine(info);
        if (meta) root.appendChild(meta);
        return root;
    }

    // ---------- public API ----------

    window.showScreeningOutput = function(screening) {
        if (!screening) return;

        document.getElementById('screening-output-name').textContent = screening.name || '-';
        document.getElementById('screening-output-meta').textContent =
            [screening.type, screening.ai_model, screening.date].filter(v => v && v !== '-').join(' · ');

        const readable = document.getElementById('screening-output-readable');
        const pre = document.getElementById('screening-output-json');
        const tabs = document.getElementById('screening-output-tabs');
        const tabContent = readable.parentElement;
        const empty = document.getElementById('screening-output-empty');
        const copyBtn = document.getElementById('screening-output-copy');
        const hasOutput = screening.output_ai !== null && screening.output_ai !== undefined && String(screening.output_ai).trim() !== '';

        readable.innerHTML = '';
        if (hasOutput) {
            pre.textContent = formatRaw(screening.output_ai);
            try {
                readable.appendChild(renderReadable(screening.output_ai));
            } catch (e) {
                readable.appendChild(el('p', 'text-muted', 'Could not build the readable view. See the Raw JSON tab.'));
            }
            tabs.classList.remove('d-none');
            tabContent.classList.remove('d-none');
            empty.classList.add('d-none');
            copyBtn.disabled = false;
            bootstrap.Tab.getOrCreateInstance(document.getElementById('screening-output-readable-tab')).show();
        } else {
            pre.textContent = '';
            tabs.classList.add('d-none');
            tabContent.classList.add('d-none');
            empty.textContent = screening.type === 'Human'
                ? 'This is a human screening, so there is no model output.'
                : 'No model output available for this study in this screening.';
            empty.classList.remove('d-none');
            copyBtn.disabled = true;
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('screeningOutputModal')).show();
    };

    window.copyScreeningOutput = function() {
        const text = document.getElementById('screening-output-json').textContent;
        if (!text || !navigator.clipboard) return;
        const btn = document.getElementById('screening-output-copy');
        navigator.clipboard.writeText(text).then(() => {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="icon-base ri ri-check-line me-1"></i>Copied';
            setTimeout(() => { btn.innerHTML = original; }, 1500);
        });
    };

    // Keep the output modal (and its backdrop) above the Study Details modal,
    // relative to whatever z-index the theme gives to the already-open modal
    document.addEventListener('show.bs.modal', function(e) {
        if (e.target.id !== 'screeningOutputModal') return;
        let baseZ = 0;
        document.querySelectorAll('.modal.show').forEach(function(m) {
            if (m !== e.target) baseZ = Math.max(baseZ, parseInt(getComputedStyle(m).zIndex, 10) || 0);
        });
        if (!baseZ) return;
        e.target.style.zIndex = baseZ + 10;
        setTimeout(() => {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length) backdrops[backdrops.length - 1].style.zIndex = baseZ + 5;
        }, 0);
    });
})();
</script>

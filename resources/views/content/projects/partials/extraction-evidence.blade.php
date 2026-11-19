{{--
    Highlights, in a rendered study article, the passages the AI quoted for each extraction field
    (extraction_study.evidence), one color per field. Used by the extraction study window.

    window.extractionEvidence.colorFor(index)            color of the field at that position
    window.extractionEvidence.highlight(article, fields) fields: [{id, label, color, quotes: []}]
                                                          returns {fieldId: {found, total}}
    window.extractionEvidence.jump(article, fieldId, quoteIndex?)  scrolls to a highlighted passage
--}}
<style>
    mark.evidence-mark {
        padding: 0 1px;
        border-radius: 2px;
        color: inherit;
        background-color: color-mix(in srgb, var(--evidence-color) 28%, transparent);
        box-shadow: inset 0 -2px 0 var(--evidence-color);
        scroll-margin-top: 6rem;
    }
    mark.evidence-mark mark.evidence-mark { box-shadow: inset 0 -2px 0 var(--evidence-color), 0 2px 0 var(--evidence-color); }
    mark.evidence-mark.evidence-flash { outline: 2px solid var(--evidence-color); outline-offset: 1px; }
    .evidence-off mark.evidence-mark { background-color: transparent; box-shadow: none; }
    .evidence-legend {
        position: sticky;
        top: 0;
        z-index: 2;
        display: flex;
        flex-wrap: wrap;
        gap: .375rem;
        align-items: center;
        padding: .5rem 1.5rem;
        background-color: var(--bs-body-bg);
        border-bottom: 1px solid var(--bs-border-color);
        font-size: .8125rem;
    }
    .evidence-chip {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 1rem;
        padding: .125rem .625rem;
        background: transparent;
        color: inherit;
        line-height: 1.4;
    }
    .evidence-chip:disabled { opacity: .55; }
    .evidence-swatch {
        display: inline-block;
        width: .75rem;
        height: .75rem;
        border-radius: 50%;
        flex-shrink: 0;
        background-color: var(--evidence-color);
    }
    .evidence-quotes { font-size: .8125rem; }
    .evidence-quotes li { margin-bottom: .25rem; }
    .evidence-quote-link { text-align: left; font-style: italic; }
</style>

<script>
window.extractionEvidence = (function() {
    // Distinct hues; with more fields than colors, colors repeat (the legend tells them apart)
    const palette = ['#3b82f6', '#f97316', '#22c55e', '#a855f7', '#ef4444', '#14b8a6',
        '#ec4899', '#eab308', '#6366f1', '#84cc16', '#06b6d4', '#a16207'];
    const MIN_LENGTH = 8;        // shorter normalized passages would match by chance
    const jumpPositions = {};    // fieldId -> index of the last passage jumped to

    // Letters and digits only, lowercase, without accents or ligatures: tolerant to line breaks,
    // hyphenation, Markdown symbols and punctuation differences between the quote and the text
    function normalizeChar(ch) {
        return ch.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^\p{L}\p{N}]/gu, '');
    }

    function normalize(text) {
        let out = '';
        for (const ch of text) out += normalizeChar(ch);
        return out;
    }

    // Normalized text of the article, with the text node and offset of each normalized character
    function buildIndex(root) {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: node => node.parentElement && node.parentElement.closest('.evidence-legend')
                ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT
        });
        const chars = [];
        const nodes = [];
        const offsets = [];
        let node;
        while ((node = walker.nextNode())) {
            const text = node.nodeValue;
            let offset = 0;
            for (const ch of text) {
                const normalized = normalizeChar(ch);
                for (const c of normalized) {
                    chars.push(c);
                    nodes.push(node);
                    offsets.push(offset);
                }
                offset += ch.length;
            }
        }
        return { text: chars.join(''), nodes, offsets };
    }

    // Longest beginning (or end) of the needle found in the text, by binary search: if a
    // beginning of length k is in the text, every shorter one is too
    function longestPrefix(text, needle) {
        let low = 0, high = needle.length, at = -1;
        while (low < high) {
            const mid = Math.ceil((low + high) / 2);
            const found = text.indexOf(needle.slice(0, mid));
            if (found >= 0) { low = mid; at = found; } else { high = mid - 1; }
        }
        return { length: low, at: low ? (at >= 0 ? at : text.indexOf(needle.slice(0, low))) : -1 };
    }

    function longestSuffix(text, needle, from) {
        let low = 0, high = needle.length, at = -1;
        while (low < high) {
            const mid = Math.ceil((low + high) / 2);
            const found = text.indexOf(needle.slice(-mid), from);
            if (found >= 0) { low = mid; at = found; } else { high = mid - 1; }
        }
        return { length: low, at: low ? (at >= 0 ? at : text.indexOf(needle.slice(-low), from)) : -1 };
    }

    // Normalized [start, end] range of one part of a quote, or null
    function locatePart(text, needle) {
        const exact = text.indexOf(needle);
        if (exact >= 0) return [exact, exact + needle.length - 1];

        // The quote was cut in the Markdown (a table or page break in the middle of the sentence)
        // or slightly reworded: match its beginning and its end
        const enough = Math.max(25, Math.ceil(needle.length * 0.5));
        const prefix = longestPrefix(text, needle);
        if (prefix.length >= 15) {
            const suffix = longestSuffix(text, needle, prefix.at + prefix.length);
            const end = suffix.at + suffix.length - 1;
            if (suffix.length >= 12 && end - prefix.at + 1 <= needle.length * 1.5) {
                return [prefix.at, end];
            }
            if (prefix.length >= enough) return [prefix.at, prefix.at + prefix.length - 1];
        }
        const suffix = longestSuffix(text, needle, 0);
        if (suffix.length >= enough) return [suffix.at, suffix.at + suffix.length - 1];
        return null;
    }

    // Normalized [start, end] ranges of a quote; "..." splits it into parts found separately
    function locate(index, quote) {
        const ranges = [];
        quote.split(/\.{3,}|…/).forEach(part => {
            const needle = normalize(part);
            if (needle.length < MIN_LENGTH) return;
            const range = locatePart(index.text, needle);
            if (range) ranges.push(range);
        });
        return ranges;
    }

    // Wrap the text between two normalized positions in <mark>s (one per text node it spans)
    function wrap(root, index, range, attributes) {
        const startNode = index.nodes[range[0]];
        const endNode = index.nodes[range[1]];
        const startOffset = index.offsets[range[0]];
        const endText = endNode.nodeValue;
        let endOffset = index.offsets[range[1]];
        endOffset += endText.codePointAt(endOffset) > 0xffff ? 2 : 1;

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        walker.currentNode = startNode;
        const textNodes = [];
        let node = startNode;
        while (node) {
            textNodes.push(node);
            if (node === endNode) break;
            node = walker.nextNode();
        }

        const marks = [];
        textNodes.forEach(textNode => {
            let target = textNode;
            const from = textNode === startNode ? startOffset : 0;
            const to = textNode === endNode ? endOffset : textNode.nodeValue.length;
            if (to <= from || !textNode.nodeValue.slice(from, to).trim()) return;
            if (to < target.nodeValue.length) target.splitText(to);
            if (from > 0) target = target.splitText(from);

            const mark = document.createElement('mark');
            mark.className = 'evidence-mark';
            Object.entries(attributes).forEach(([name, value]) => mark.setAttribute(name, value));
            mark.style.setProperty('--evidence-color', attributes['data-color']);
            target.parentNode.insertBefore(mark, target);
            mark.appendChild(target);
            marks.push(mark);
        });
        return marks;
    }

    function legend(article, fields, results) {
        const bar = document.createElement('div');
        bar.className = 'evidence-legend';
        bar.innerHTML = '<div class="form-check form-switch mb-0 me-2">' +
            '<input class="form-check-input" type="checkbox" id="evidence-toggle" checked>' +
            '<label class="form-check-label" for="evidence-toggle">AI passages</label></div>';

        fields.filter(field => field.quotes.length).forEach(field => {
            const result = results[field.id];
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'evidence-chip';
            chip.style.setProperty('--evidence-color', field.color);
            chip.disabled = result.found === 0;
            chip.title = result.found === 0
                ? 'The quoted passages were not found in the article'
                : 'Go to the next passage of this field';
            chip.innerHTML = '<span class="evidence-swatch"></span><span></span><span class="text-muted"></span>';
            chip.children[1].textContent = field.label;
            chip.children[2].textContent = result.found + '/' + result.total;
            chip.addEventListener('click', () => api.jump(article, field.id));
            bar.appendChild(chip);
        });

        bar.querySelector('#evidence-toggle').addEventListener('change', event => {
            article.classList.toggle('evidence-off', !event.target.checked);
        });
        article.parentNode.insertBefore(bar, article);
    }

    const api = {
        colorFor(index) {
            return palette[index % palette.length];
        },

        highlight(article, fields) {
            const results = {};
            if (!article) return results;
            fields.forEach(field => {
                results[field.id] = { found: 0, total: field.quotes.length };
                field.quotes.forEach((quote, quoteIndex) => {
                    // Rebuilt for each quote, since wrapping splits the text nodes
                    const index = buildIndex(article);
                    const ranges = locate(index, quote);
                    let marked = false;
                    ranges.forEach(range => {
                        const marks = wrap(article, index, range, {
                            'data-field': String(field.id),
                            'data-quote': String(quoteIndex),
                            'data-color': field.color,
                            title: field.label,
                        });
                        marked = marked || marks.length > 0;
                    });
                    if (marked) results[field.id].found++;
                });
                jumpPositions[field.id] = -1;
            });
            if (fields.some(field => field.quotes.length)) legend(article, fields, results);
            return results;
        },

        // Scroll to a passage of the field: the given one, or the next one on each call
        jump(article, fieldId, quoteIndex) {
            if (!article) return false;
            const marks = Array.from(article.querySelectorAll('mark.evidence-mark[data-field="' + fieldId + '"]'));
            if (!marks.length) return false;

            let target;
            if (quoteIndex !== undefined) {
                target = marks.find(mark => mark.dataset.quote === String(quoteIndex));
                if (!target) return false;
            } else {
                // One stop per passage, not per <mark> piece of it
                const starts = marks.filter((mark, i) => i === 0 || marks[i - 1].dataset.quote !== mark.dataset.quote);
                jumpPositions[fieldId] = ((jumpPositions[fieldId] ?? -1) + 1) % starts.length;
                target = starts[jumpPositions[fieldId]];
            }

            article.classList.remove('evidence-off');
            const toggle = article.parentNode.querySelector('#evidence-toggle');
            if (toggle) toggle.checked = true;

            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const passage = marks.filter(mark => mark.dataset.quote === target.dataset.quote);
            passage.forEach(mark => mark.classList.add('evidence-flash'));
            setTimeout(() => passage.forEach(mark => mark.classList.remove('evidence-flash')), 1600);
            return true;
        },
    };

    return api;
})();
</script>

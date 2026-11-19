{{--
    "Article (Markdown)" tab next to the PDF in a study window.
    Markup per window: tab button with data-markdown-tab="<prefix>" and a container #<prefix>-md-content.
    When the window shows a study, call window.studyMarkdownTab.show('<prefix>', study).
    The Markdown is fetched (and the renderer loaded) only when the tab is opened.
--}}
<style>
    /* Panes are pinned to the area below the tabs instead of using height: 100%, which does not
       resolve inside the modal's wrapping .row, so a long article grew past the window and was
       clipped by the column's overflow: hidden instead of scrolling. */
    .study-left-tabs { flex: 1; min-height: 0; position: relative; }
    .study-left-tabs > .tab-pane.active { display: flex; flex-direction: column; position: absolute; inset: 0; }
    .study-markdown-pane { overflow-y: auto; overscroll-behavior: contain; background-color: var(--bs-body-bg); }
    .study-markdown-pane > div { flex-shrink: 0; }
    .study-markdown { max-width: 920px; margin: 0 auto; line-height: 1.65; overflow-wrap: anywhere; }
    .study-markdown h1, .study-markdown h2, .study-markdown h3,
    .study-markdown h4, .study-markdown h5, .study-markdown h6 { margin-top: 1.5rem; margin-bottom: .75rem; }
    .study-markdown h1 { font-size: 1.6rem; }
    .study-markdown h2 { font-size: 1.35rem; }
    .study-markdown h3 { font-size: 1.15rem; }
    .study-markdown h4, .study-markdown h5, .study-markdown h6 { font-size: 1rem; }
    .study-markdown table { font-size: .85rem; }
    .study-markdown .table-wrapper { overflow-x: auto; margin-bottom: 1rem; }
    .study-markdown pre { white-space: pre-wrap; background: var(--bs-tertiary-bg, #f5f6f8); padding: .75rem; border-radius: .375rem; }
    .study-markdown img { max-width: 100%; }
    .study-markdown blockquote { border-left: 3px solid var(--bs-border-color); padding-left: 1rem; color: var(--bs-secondary-color); }
</style>

<script>
window.studyMarkdownTab = (function() {
    const projectId = @json($project->id);
    const libraries = [
        ['marked', 'https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js'],
        ['DOMPurify', 'https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js'],
    ];
    const studies = {};      // prefix -> study shown in that window
    const loadedFor = {};    // prefix -> study id whose Markdown is on screen (or loading)
    const cache = {};        // study id -> Markdown already fetched
    let librariesReady = null;

    function loadLibraries() {
        if (!librariesReady) {
            librariesReady = Promise.all(libraries.map(([globalName, src]) => window[globalName]
                ? Promise.resolve()
                : new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = src;
                    script.onload = resolve;
                    script.onerror = () => reject(new Error('Could not load ' + src));
                    document.head.appendChild(script);
                })));
            librariesReady.catch(() => { librariesReady = null; });
        }
        return librariesReady;
    }

    function container(prefix) {
        return document.getElementById(prefix + '-md-content');
    }

    function message(prefix, icon, text, extraClass) {
        const box = container(prefix);
        if (!box) return;
        box.innerHTML = '<div class="d-flex align-items-center justify-content-center text-center text-muted p-5 ' + (extraClass || '') + '">' +
            '<div><i class="icon-base ri ' + icon + ' icon-3x mb-3"></i><p class="mb-0"></p></div></div>';
        box.querySelector('p').textContent = text;
    }

    function render(prefix, markdown) {
        const box = container(prefix);
        if (!box) return;
        const html = window.DOMPurify.sanitize(window.marked.parse(markdown, { gfm: true }));
        box.innerHTML = '<article class="study-markdown p-4">' + html + '</article>';
        box.querySelectorAll('table').forEach(table => {
            table.classList.add('table', 'table-sm', 'table-bordered');
            const wrapper = document.createElement('div');
            wrapper.className = 'table-wrapper';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
        box.querySelectorAll('a[href]').forEach(link => {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        });
        box.closest('.study-markdown-pane')?.scrollTo(0, 0);
    }

    function load(prefix) {
        const study = studies[prefix];
        if (!study) return;
        if (loadedFor[prefix] === study.id) return;
        loadedFor[prefix] = study.id;

        if (!study.pdf_path) {
            message(prefix, 'ri-file-text-line', 'No PDF available for this study');
            return;
        }

        const stillCurrent = () => studies[prefix] && studies[prefix].id === study.id;
        message(prefix, 'ri-loader-4-line', cache[study.id] ? 'Rendering...' : 'Loading article... (converting the PDF can take a few seconds the first time)');

        const markdownRequest = cache[study.id] !== undefined
            ? Promise.resolve({ success: true, markdown: cache[study.id] })
            : fetch('/projects/' + projectId + '/studies/' + study.id + '/markdown', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(response => response.json());

        Promise.all([markdownRequest, loadLibraries()])
            .then(([data]) => {
                if (!stillCurrent()) return;
                if (!data.success) {
                    loadedFor[prefix] = null;
                    message(prefix, 'ri-error-warning-line', data.message || 'Could not load the article.');
                    return;
                }
                cache[study.id] = data.markdown;
                render(prefix, data.markdown);
            })
            .catch(error => {
                console.error('Study Markdown:', error);
                if (!stillCurrent()) return;
                loadedFor[prefix] = null;
                message(prefix, 'ri-error-warning-line', 'Could not load the article. Please try again.');
            });
    }

    // Load when the tab is opened
    document.addEventListener('shown.bs.tab', function(event) {
        const prefix = event.target.dataset.markdownTab;
        if (prefix) load(prefix);
    });

    return {
        // Called when a window shows a study (open, Previous/Next)
        show(prefix, study) {
            studies[prefix] = study;
            loadedFor[prefix] = null;
            const tab = document.querySelector('[data-markdown-tab="' + prefix + '"]');
            if (tab && tab.classList.contains('active')) {
                load(prefix);
            } else {
                const box = container(prefix);
                if (box) box.innerHTML = '';
            }
        }
    };
})();
</script>

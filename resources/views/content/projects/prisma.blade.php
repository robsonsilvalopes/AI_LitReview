@extends('layouts/contentNavbarLayout')

@section('title', $project->name . ' - PRISMA 2020')

@section('page-style')
<style>
    #prismaFlowSvg { background: #fff; border: 1px solid var(--bs-border-color); border-radius: .5rem; overflow: auto; padding: .5rem; }
    #prismaFlowSvg svg { display: block; margin: 0 auto; max-width: 100%; height: auto; }
    .prisma-section-row td { background: var(--bs-light-bg-subtle, #f3f6f9); font-weight: 600; letter-spacing: .03em; }
    .prisma-checklist td { vertical-align: top; }
    .prisma-checklist .item-text { min-width: 280px; }
    .prisma-checklist textarea { min-width: 200px; }
    .prisma-suggestion pre { white-space: pre-wrap; font-size: .78rem; background: var(--bs-light-bg-subtle, #f5f5f5); padding: .5rem; border-radius: .35rem; margin: .35rem 0 0; max-height: 260px; overflow: auto; }
</style>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h2 class="card-title mb-1">{{ $project->name }}</h2>
                <div class="text-muted" style="font-size: 0.95rem;">PRISMA 2020 — flow diagram and checklist</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('projects.report', $project) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="icon-base ri ri-arrow-left-line me-1"></i>Report
                </a>
                <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-secondary">Project</a>
            </div>
        </div>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#prismaFlowTab" type="button" role="tab">
                    <i class="icon-base ri ri-flow-chart me-1"></i>Flow diagram
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#prismaChecklistTab" type="button" role="tab">
                    <i class="icon-base ri ri-list-check-3 me-1"></i>Checklist
                    <span class="badge bg-label-primary ms-1" id="prismaChecklistBadge"></span>
                </button>
            </li>
        </ul>

        <div class="tab-content px-0 pb-0">
            {{-- ===================================================== FLOW DIAGRAM --}}
            <div class="tab-pane fade show active" id="prismaFlowTab" role="tabpanel">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="text-muted small me-auto">
                        Numbers computed from the decisions recorded in each phase{{ !empty(array_filter($flow['adjusted'])) || $flow['reasons_text'] ? ', with your adjustments' : '' }}.
                    </span>
                    <a href="{{ route('projects.prisma.flow-svg', $project) }}" class="btn btn-sm btn-outline-primary">
                        <i class="icon-base ri ri-download-2-line me-1"></i>Download SVG
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="prismaPngBtn">
                        <i class="icon-base ri ri-image-line me-1"></i>Download PNG
                    </button>
                    @if($canEdit)
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#prismaAdjust">
                        <i class="icon-base ri ri-equalizer-line me-1"></i>Adjust numbers
                    </button>
                    @endif
                </div>

                @foreach($flow['warnings'] as $warning)
                <div class="alert alert-warning py-2 small mb-2"><i class="icon-base ri ri-error-warning-line me-1"></i>{{ $warning }}</div>
                @endforeach

                @if($canEdit)
                <div class="collapse mb-3" id="prismaAdjust">
                    <div class="border rounded p-3">
                        <p class="small text-muted mb-3">
                            Change a number when part of the process happened outside the tool (for example, records removed by another tool, or registers searched).
                            Leave a field empty to use the computed value.
                        </p>
                        <form id="prismaFlowForm">
                            <div class="row g-2">
                                @foreach($adjustable as $key => $label)
                                <div class="col-md-4 col-lg-3">
                                    <label class="form-label small mb-1" for="flow_{{ $key }}">{{ $label }}</label>
                                    <input type="number" min="0" class="form-control form-control-sm" id="flow_{{ $key }}" name="{{ $key }}"
                                           value="{{ $project->prisma_flow['numbers'][$key] ?? '' }}" placeholder="{{ $flow['computed'][$key] }} (computed)">
                                </div>
                                @endforeach
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="flow_reasons_text">Reasons for excluding reports (optional, one per line as <code>Reason: 12</code>)</label>
                                    <textarea class="form-control form-control-sm" id="flow_reasons_text" rows="3"
                                              placeholder="Empty: the exclusion criteria marked in the final selection">{{ $flow['reasons_text'] }}</textarea>
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">Save and redraw</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="prismaFlowResetBtn">Use computed values</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif

                <div id="prismaFlowSvg">{!! $svg !!}</div>
                <p class="small text-muted mt-2 mb-0">
                    Layout of the PRISMA 2020 flow diagram for new systematic reviews which included searches of databases and registers only
                    (Page MJ, et al. BMJ 2021;372:n71). "Reports not retrieved" are studies included in the initial selection without a PDF or a final decision;
                    the reasons for excluding reports are the exclusion criteria marked in the final selection.
                </p>
            </div>

            {{-- ===================================================== CHECKLIST --}}
            <div class="tab-pane fade" id="prismaChecklistTab" role="tabpanel">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="small text-muted me-auto" id="prismaChecklistSummary"></span>
                    <a href="{{ route('projects.prisma.checklist-xlsx', $project) }}" class="btn btn-sm btn-outline-primary">
                        <i class="icon-base ri ri-file-excel-2-line me-1"></i>Export XLSX
                    </a>
                    @if($canEdit)
                    <button type="button" class="btn btn-sm btn-primary" id="prismaChecklistSaveBtn">
                        <i class="icon-base ri ri-save-line me-1"></i>Save checklist
                    </button>
                    @endif
                </div>
                <div class="alert alert-info small py-2">
                    For each item, mark whether it is reported and where (section, page, or table of the manuscript).
                    <strong>Recorded in the tool</strong> shows what this project already documents for the item, to adapt in the text.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered prisma-checklist mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 14%;">Topic</th>
                                <th style="width: 50px;">#</th>
                                <th>Checklist item</th>
                                <th style="width: 150px;">Status</th>
                                <th style="width: 18%;">Location where item is reported</th>
                                <th style="width: 18%;">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $section = null; @endphp
                            @foreach($rows as $row)
                                @if($row['section'] !== $section)
                                    @php $section = $row['section']; @endphp
                                    <tr class="prisma-section-row"><td colspan="6">{{ $section }}</td></tr>
                                @endif
                                <tr data-item="{{ $row['id'] }}">
                                    <td class="small fw-medium">{{ $row['show_topic'] ? $row['topic'] : '' }}</td>
                                    <td class="small fw-medium">{{ $row['id'] }}</td>
                                    <td class="small item-text">
                                        {{ $row['text'] }}
                                        @if($row['suggestion'])
                                        <details class="prisma-suggestion mt-1">
                                            <summary class="text-primary">Recorded in the tool</summary>
                                            <pre>{{ $row['suggestion'] }}</pre>
                                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 mt-1" style="font-size: .75rem;" data-copy-item="{{ $row['id'] }}">
                                                <i class="icon-base ri ri-file-copy-line me-1"></i>Copy
                                            </button>
                                        </details>
                                        @endif
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" data-field="status" @disabled(!$canEdit)>
                                            <option value="">—</option>
                                            @foreach($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected($row['status'] === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" data-field="location" maxlength="500"
                                               value="{{ $row['location'] }}" placeholder="e.g. Methods, p. 4" @disabled(!$canEdit)>
                                    </td>
                                    <td>
                                        <textarea class="form-control form-control-sm" data-field="notes" rows="1" maxlength="5000" @disabled(!$canEdit)>{{ $row['notes'] }}</textarea>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    Checklist from Page MJ, McKenzie JE, Bossuyt PM, et al. The PRISMA 2020 statement: an updated guideline for reporting systematic reviews.
                    BMJ 2021;372:n71, licensed under CC BY 4.0. Item 2 has its own checklist (PRISMA 2020 for Abstracts).
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'};
    const notify = (message, type) => typeof window.showToast === 'function' ? window.showToast(message, type) : alert(message);
    const fileBase = @json(\Illuminate\Support\Str::slug($project->name) ?: 'project-' . $project->id);
    const suggestions = @json(collect($rows)->pluck('suggestion', 'id')->filter());

    // ------------------------------------------------------------ PNG export (drawn by the browser)
    document.getElementById('prismaPngBtn').addEventListener('click', function() {
        const svg = document.querySelector('#prismaFlowSvg svg');
        if (!svg) return;
        const width = parseFloat(svg.getAttribute('width'));
        const height = parseFloat(svg.getAttribute('height'));
        const scale = 2;
        const data = new XMLSerializer().serializeToString(svg);
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            canvas.width = width * scale;
            canvas.height = height * scale;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(blob => {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'prisma-flow-' + fileBase + '.png';
                a.click();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            }, 'image/png');
        };
        img.onerror = () => notify('Could not draw the PNG; use Download SVG.', 'error');
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(data);
    });

    // ------------------------------------------------------------ Flow adjustments
    const flowForm = document.getElementById('prismaFlowForm');
    function saveFlow(numbers, reasonsText) {
        return fetch(@json(route('projects.prisma.flow', $project)), {
            method: 'POST', headers, body: JSON.stringify({numbers, reasons_text: reasonsText})
        }).then(r => r.json().then(data => {
            if (!r.ok || !data.success) throw new Error(data.message || 'HTTP ' + r.status);
            return data;
        }));
    }
    if (flowForm) {
        flowForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const numbers = {};
            flowForm.querySelectorAll('input[type="number"]').forEach(input => {
                numbers[input.name] = input.value === '' ? null : parseInt(input.value, 10);
            });
            saveFlow(numbers, document.getElementById('flow_reasons_text').value)
                .then(() => { notify('Flow diagram updated.', 'success'); window.location.reload(); })
                .catch(err => notify('Error: ' + err.message, 'error'));
        });
        document.getElementById('prismaFlowResetBtn').addEventListener('click', function() {
            if (!confirm('Remove all adjustments and use the computed numbers?')) return;
            saveFlow({}, '')
                .then(() => { notify('Using the computed numbers.', 'success'); window.location.reload(); })
                .catch(err => notify('Error: ' + err.message, 'error'));
        });
    }

    // ------------------------------------------------------------ Checklist
    const rows = Array.from(document.querySelectorAll('.prisma-checklist tr[data-item]'));
    function updateSummary() {
        const counts = {reported: 0, not_applicable: 0, not_reported: 0, empty: 0};
        rows.forEach(row => {
            const status = row.querySelector('[data-field="status"]').value;
            counts[status || 'empty']++;
        });
        const done = counts.reported + counts.not_applicable;
        document.getElementById('prismaChecklistSummary').textContent =
            counts.reported + ' reported · ' + counts.not_applicable + ' not applicable · ' + counts.not_reported + ' not reported yet · ' + counts.empty + ' unanswered (of ' + rows.length + ' items)';
        document.getElementById('prismaChecklistBadge').textContent = done + '/' + rows.length;
    }
    rows.forEach(row => row.querySelector('[data-field="status"]').addEventListener('change', updateSummary));
    updateSummary();

    document.querySelectorAll('[data-copy-item]').forEach(btn => {
        btn.addEventListener('click', function() {
            const text = suggestions[btn.dataset.copyItem] || '';
            navigator.clipboard.writeText(text)
                .then(() => notify('Copied.', 'success'))
                .catch(() => notify('Could not copy; select the text instead.', 'warning'));
        });
    });

    const saveBtn = document.getElementById('prismaChecklistSaveBtn');
    if (saveBtn) {
        let dirty = false;
        rows.forEach(row => row.querySelectorAll('[data-field]').forEach(f => f.addEventListener('input', () => { dirty = true; })));
        rows.forEach(row => row.querySelector('[data-field="status"]').addEventListener('change', () => { dirty = true; }));
        window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

        saveBtn.addEventListener('click', function() {
            const items = {};
            rows.forEach(row => {
                items[row.dataset.item] = {
                    status: row.querySelector('[data-field="status"]').value,
                    location: row.querySelector('[data-field="location"]').value,
                    notes: row.querySelector('[data-field="notes"]').value
                };
            });
            saveBtn.disabled = true;
            fetch(@json(route('projects.prisma.checklist', $project)), {method: 'POST', headers, body: JSON.stringify({items})})
                .then(r => r.json().then(data => { if (!r.ok || !data.success) throw new Error(data.message || 'HTTP ' + r.status); }))
                .then(() => { dirty = false; notify('Checklist saved.', 'success'); })
                .catch(err => notify('Error saving the checklist: ' + err.message, 'error'))
                .finally(() => { saveBtn.disabled = false; });
        });
    }
});
</script>
@endsection

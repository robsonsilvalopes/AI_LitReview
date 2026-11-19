@extends('layouts/contentNavbarLayout')

@section('title', $extraction->nome . ' - Extraction')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/responsive.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/buttons.bootstrap5.min.css') }}">
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ $extraction->nome }}</h5>
                <div class="d-flex gap-2">
                    @if(in_array($extraction->tipo, ['AI OpenRouter', 'AI Local']))
                        <button type="button" class="btn btn-sm btn-primary" onclick="reprocessExtraction()">
                            <i class="icon-base ri ri-refresh-line me-1"></i>Reprocess
                        </button>
                    @endif
                    <a href="{{ route('projects.extraction', $project) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="icon-base ri ri-arrow-left-line me-1"></i>Back
                    </a>
                    @if(in_array($extraction->tipo, ['AI OpenRouter', 'AI Local']))
                    <button type="button" class="btn btn-primary" id="processPendingStudiesBtn">
                        <i class="icon-base ri ri-refresh-line me-1"></i>Reprocess pending only
                    </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Type</h6>
                        <p class="mb-0">{{ $extraction->tipo }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Date</h6>
                        <p class="mb-0">{{ $extraction->data->format('Y-m-d') }}</p>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Responsible</h6>
                        <p class="mb-0">{{ $extraction->responsavel->name }} ({{ $extraction->responsavel->email }})</p>
                    </div>
                    @if(in_array($extraction->tipo, ['AI OpenRouter', 'AI Local']))
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Temperature</h6>
                        <p class="mb-0">{{ $extraction->temperature ?? 'Not recorded' }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@include('content.projects.partials.screening-status-chart', [
    'statusItems' => $statusItems,
    'chartTitle' => 'Extraction Progress',
    'chartScope' => 'in this extraction',
])

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0">Studies</h5>
                <select id="extractionStatusFilter" class="form-select form-select-sm" style="width: auto;" aria-label="Filter by extraction status">
                    <option value="">All studies</option>
                    <option value="extracted">Extracted</option>
                    <option value="pending">Not extracted</option>
                </select>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="studiesTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>DOI</th>
                                <th>Abstract</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Study Extraction Data Modal -->
<div class="modal fade" id="studyExtractionDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 95vw;">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header">
                <h5 class="modal-title" id="studyExtractionDataModalTitle">Extraction Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="studyExtractionDataForm">
                <input type="hidden" id="study_extraction_id" name="study_id">
                <div class="modal-body p-0" style="height: calc(90vh - 120px); overflow: hidden; display: flex; flex-direction: column;">
                    <div class="row g-0 flex-grow-1" style="min-height: 0;">
                        <!-- Left side: PDF Preview -->
                        <div class="col-md-9 border-end d-flex flex-column" style="overflow: hidden; min-height: 0;">
                            <div id="study-pdf-preview-extraction" style="flex: 1; overflow: hidden; padding: 0; min-height: 0; display: flex; align-items: stretch; background-color: #f5f6f8;">
                                <div class="d-flex align-items-center justify-content-center h-100 w-100">
                                    <div class="text-center text-muted p-5">
                                        <i class="icon-base ri ri-file-pdf-line icon-3x mb-3"></i>
                                        <p class="mb-0">No PDF available for this study</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Right side: Extraction Data Fields -->
                        <div class="col-md-3" style="height: 100%; overflow: auto;">
                            <div class="p-4">
                                <div id="extractionDataFields">
                                    <!-- Fields will be populated dynamically -->
                                </div>
                                
                                <!-- AI Input and Output -->
                                <div id="aiInputOutputSection" style="display: none;">
                                    <hr class="my-4">
                                    
                                    <!-- AI Input -->
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-2">AI Input (Prompt)</h6>
                                        <div class="card bg-light">
                                            <div class="card-body p-3">
                                                <pre id="study-ai-input-extraction" class="mb-0" style="white-space: pre-wrap; font-size: 0.75rem; max-height: 200px; overflow-y: auto; background: transparent; border: none; padding: 0; font-family: 'Courier New', monospace;"></pre>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- AI Output -->
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-2">AI Output (Response)</h6>
                                        <div class="card bg-light">
                                            <div class="card-body p-3">
                                                <pre id="study-ai-output-extraction" class="mb-0" style="white-space: pre-wrap; font-size: 0.75rem; max-height: 200px; overflow-y: auto; background: transparent; border: none; padding: 0; font-family: 'Courier New', monospace;"></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-outline-secondary" onclick="navigateStudyExtraction('prev')">
                            <i class="icon-base ri ri-arrow-left-s-line"></i> Previous
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary me-2" onclick="saveAndNextExtraction()">
                            Save & Next <i class="icon-base ri ri-arrow-right-s-line"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary me-2" onclick="navigateStudyExtraction('next')">
                            Next <i class="icon-base ri ri-arrow-right-s-line"></i>
                        </button>
                        <button type="submit" class="btn btn-success">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables/js/responsive.bootstrap5.min.js') }}"></script>
@endsection

@section('page-script')
<script>
// Initialize DataTables after page loads
document.addEventListener('DOMContentLoaded', function() {
    const jquerySrc = "{{ asset('assets/vendor/libs/jquery/jquery.min.js') }}";
    const dataTablesScripts = [
        "{{ asset('assets/vendor/libs/datatables/js/jquery.dataTables.min.js') }}",
        "{{ asset('assets/vendor/libs/datatables/js/dataTables.bootstrap5.min.js') }}",
        "{{ asset('assets/vendor/libs/datatables/js/dataTables.responsive.min.js') }}",
        "{{ asset('assets/vendor/libs/datatables/js/responsive.bootstrap5.min.js') }}"
    ];

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    function ensureJQuery() {
        if (window.jQuery) {
            return Promise.resolve();
        }
        return loadScript(jquerySrc);
    }

    function ensureDataTables() {
        if (window.jQuery && jQuery.fn && (jQuery.fn.DataTable || jQuery.fn.dataTable)) {
            return Promise.resolve();
        }
        return dataTablesScripts.reduce(
            (promise, src) => promise.then(() => loadScript(src)),
            Promise.resolve()
        );
    }

    const projectId = {{ $project->id }};
    const extractionId = {{ $extraction->id }};
    const extractionData = @json($extractionData);
    // Pre-load studies with extraction_data_values from the controller
    const preloadedStudies = @json($studies);
    let studiesTable = null;
    let allStudiesForNavigation = [];
    let currentStudyData = null;
    let currentStudyRowIndex = -1;

    function initStudiesTable() {
        console.log('initStudiesTable called');
        
        if (typeof jQuery === 'undefined' || typeof jQuery.fn === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
            console.error('jQuery or DataTables not available for studies table');
            console.error('jQuery:', typeof jQuery);
            if (typeof jQuery !== 'undefined') {
                console.error('jQuery.fn:', typeof jQuery.fn);
                if (typeof jQuery.fn !== 'undefined') {
                    console.error('jQuery.fn.DataTable:', typeof jQuery.fn.DataTable);
                } else {
                    console.error('jQuery.fn: N/A');
                }
            } else {
                console.error('jQuery.fn: N/A');
                console.error('jQuery.fn.DataTable: N/A');
            }
            return;
        }

        const $table = jQuery('#studiesTable');
        if ($table.length === 0) {
            console.error('Table element #studiesTable not found');
            return;
        }
        
        console.log('Initializing DataTable with projectId:', projectId);
        console.log('AJAX URL will be:', '/projects/' + projectId + '/studies');
        
        studiesTable = $table.DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
            url: '/projects/' + projectId + '/studies',
            type: 'GET',
            data: function(d) {
                d.filter_final_included = true;
                d.extraction_id = extractionId;
                d.extraction_status = document.getElementById('extractionStatusFilter').value;
                return d;
            },
            headers: {
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            dataSrc: function(json) {
                console.log('DataTables response:', json);
                if (!json || !json.data || !Array.isArray(json.data)) {
                    console.warn('Invalid DataTables response format:', json);
                    return [];
                }
                
                // Merge extraction_data_values, input_ai, and output_ai from preloaded studies
                const mergedData = json.data.map(function(study) {
                    const preloadedStudy = preloadedStudies.find(s => s.id === study.id);
                    if (preloadedStudy) {
                        if (preloadedStudy.extraction_data_values) {
                            study.extraction_data_values = preloadedStudy.extraction_data_values;
                        }
                        if (preloadedStudy.input_ai !== undefined) {
                            study.input_ai = preloadedStudy.input_ai;
                        }
                        if (preloadedStudy.output_ai !== undefined) {
                            study.output_ai = preloadedStudy.output_ai;
                        }
                    }
                    return study;
                });
                
                allStudiesForNavigation = mergedData;
                console.log('Merged studies with extraction_data_values:', mergedData);
                return mergedData;
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
                console.error('Response status:', xhr.status);
                console.error('Response text:', xhr.responseText);
            }
        },
        columns: [
            { data: 'custom_id', name: 'custom_id', orderable: true, searchable: true },
            { data: 'name', name: 'name', orderable: true, searchable: true },
            { data: 'doi', name: 'doi', orderable: true, searchable: true },
            { data: 'abstract', name: 'abstract', orderable: false, searchable: true },
            { data: 'id', name: 'actions', orderable: false, searchable: false }
        ],
        columnDefs: [
            {
                targets: 0,
                render: function(data, type, row) {
                    return '<div class="d-flex align-items-center">' + extractionStatusIcon(row) +
                        '<span>' + (data || 'N/A') + '</span></div>';
                }
            },
            {
                targets: 1,
                render: function(data, type, row) {
                    const title = data || 'N/A';
                    return '<a href="javascript:void(0);" onclick="openStudyExtractionModal(' + row.id + ')" class="text-primary fw-semibold" style="cursor: pointer; text-decoration: none;">' + title + '</a>';
                }
            },
            {
                targets: 2,
                render: function(data, type, row) {
                    if (!data) return 'N/A';
                    const doiText = data.length > 30 ? data.substring(0, 30) + '...' : data;
                    const doiUrl = 'https://doi.org/' + encodeURIComponent(data.trim());
                    return '<a href="' + doiUrl + '" target="_blank" rel="noopener" class="text-primary" style="text-decoration: none;">' + doiText + '</a>';
                }
            },
            {
                targets: 3,
                render: function(data, type, row) {
                    if (!data) return 'N/A';
                    const abstractText = data.length > 150 ? data.substring(0, 150) + '...' : data;
                    return '<span title="' + (data || '').replace(/"/g, '&quot;') + '">' + abstractText + '</span>';
                }
            },
            {
                targets: 4,
                render: function(data, type, row) {
                    return '<button type="button" class="btn btn-sm btn-label-primary" onclick="openStudyExtractionModal(' + data + ')">' +
                        '<i class="icon-base ri ri-edit-line me-1"></i>Extract</button>';
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, 100], [10, 15, 25, 50, 100]],
        language: {
            search: "Search:",
            lengthMenu: "_MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: '<i class="icon-base ri ri-skip-left-line"></i>',
                last: '<i class="icon-base ri ri-skip-right-line"></i>',
                next: '<i class="icon-base ri ri-arrow-right-s-line"></i>',
                previous: '<i class="icon-base ri ri-arrow-left-s-line"></i>'
            }
        }
    });
    } // End of initStudiesTable function

    window.studiesTable = studiesTable;

    // Green check when the study is extracted, grey empty circle when it is not
    function extractionStatusIcon(study) {
        const icon = study.extracted
            ? '<i class="icon-base ri ri-checkbox-circle-fill text-success" title="Extracted"></i>'
            : '<i class="icon-base ri ri-checkbox-blank-circle-line text-muted" title="Not extracted"></i>';
        return '<span class="me-2" data-extraction-status="' + study.id + '">' + icon + '</span>';
    }

    // Reload the list when the filter changes, and after a save once the modal closes
    // (so a filtered list drops or gains the study just saved)
    let extractionSavedSinceOpen = false;
    document.getElementById('extractionStatusFilter').addEventListener('change', function() {
        if (studiesTable) studiesTable.ajax.reload();
    });
    document.getElementById('studyExtractionDataModal').addEventListener('hidden.bs.modal', function() {
        if (extractionSavedSinceOpen && studiesTable) {
            studiesTable.ajax.reload(null, false);
        }
        extractionSavedSinceOpen = false;
    });

    // Open study extraction modal
    window.openStudyExtractionModal = function(studyId) {
        const study = allStudiesForNavigation.find(s => s.id === studyId);
        if (!study) {
            alert('Study not found');
            return;
        }

        const rowIndex = allStudiesForNavigation.findIndex(s => s.id === studyId);
        currentStudyData = study;
        currentStudyRowIndex = rowIndex >= 0 ? rowIndex : 0;

        document.getElementById('study_extraction_id').value = study.id;
        document.getElementById('studyExtractionDataModalTitle').textContent = 'Extraction Data - ' + (study.custom_id || study.id);

        // Load PDF preview
        const pdfPreview = document.getElementById('study-pdf-preview-extraction');
        if (study.pdf_path) {
            const pdfUrl = '/storage/s3/' + encodeURIComponent(study.pdf_path);
            pdfPreview.style.padding = '0';
            pdfPreview.style.overflow = 'hidden';
            pdfPreview.style.backgroundColor = 'transparent';
            pdfPreview.innerHTML = '<embed src="' + pdfUrl + '#toolbar=0&view=FitH" type="application/pdf" style="width: 100%; height: 100%; border: none; flex: 1; min-height: 0;" />';
        } else {
            pdfPreview.style.padding = '0';
            pdfPreview.style.backgroundColor = '#f5f6f8';
            pdfPreview.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 w-100"><div class="text-center text-muted p-5"><i class="icon-base ri ri-file-pdf-line icon-3x mb-3"></i><p class="mb-0">No PDF available for this study</p></div></div>';
        }

        // Populate extraction data fields
        populateExtractionDataFields(study);

        // Populate AI Input and Output
        populateAIInputOutput(study);

        // Update navigation buttons
        updateExtractionNavigationButtons();

        const modal = new bootstrap.Modal(document.getElementById('studyExtractionDataModal'));
        modal.show();
    };

    function populateAIInputOutput(study) {
        const aiInputElement = document.getElementById('study-ai-input-extraction');
        const aiOutputElement = document.getElementById('study-ai-output-extraction');
        const aiSection = document.getElementById('aiInputOutputSection');

        if (study.input_ai || study.output_ai) {
            aiSection.style.display = 'block';
            
            if (study.input_ai) {
                aiInputElement.textContent = study.input_ai;
            } else {
                aiInputElement.textContent = 'No AI input available';
            }
            
            if (study.output_ai) {
                // Remove leading whitespace/newlines before JSON
                let output = String(study.output_ai);
                // Try to find JSON in the output and clean it
                const jsonMatch = output.match(/\{[\s\S]*\}/);
                if (jsonMatch) {
                    // If JSON is found, remove everything before it and trim
                    const jsonStart = output.indexOf(jsonMatch[0]);
                    output = output.substring(jsonStart).trim();
                } else {
                    // If no JSON found, just trim leading whitespace
                    output = output.trim();
                }
                aiOutputElement.textContent = output;
            } else {
                aiOutputElement.textContent = 'No AI output available';
            }
        } else {
            aiSection.style.display = 'none';
        }
    }

    function populateExtractionDataFields(study) {
        const container = document.getElementById('extractionDataFields');
        container.innerHTML = '';

        if (!extractionData || extractionData.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No extraction data fields defined for this project.</div>';
            return;
        }

        const extractionDataValues = study.extraction_data_values || {};
        console.log('Study extraction_data_values:', extractionDataValues);
        console.log('Study object:', study);

        // Add study info at the top
        let html = '<div class="mb-3">' +
            '<span class="badge bg-label-primary mb-2">' + (study.custom_id || 'ID: ' + study.id) + '</span>' +
            '<h6 class="mb-2">' + (study.name || 'N/A') + '</h6>' +
            '</div>';

        extractionData.forEach(function(data) {
            // Try to get value - check both string and number keys
            const value = extractionDataValues[data.id] || extractionDataValues[String(data.id)] || '';
            const fieldId = 'extraction_data_' + data.id;
            
            console.log('Extraction data field:', data.id, 'Value:', value);

            html += '<div class="mb-3">' +
                '<label for="' + fieldId + '" class="form-label mb-1">' +
                '<strong>' + (data.custom_id || 'ED' + data.id) + '</strong> - ' + data.name +
                '</label>';

            if (data.note) {
                html += '<small class="text-muted d-block mb-1">' + data.note + '</small>';
            }

            // Mostrar a resposta da IA (valor atual salvo em study_extraction_data)
            if (value && value !== '') {
                const aiValue = String(value).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                html += '<div class="alert alert-info py-2 px-3 mb-2" style="font-size: 0.875rem;">' +
                    '<strong>AI answer:</strong> <span class="text-dark">' + aiValue + '</span>' +
                    '</div>';
            } else {
                html += '<div class="alert alert-warning py-2 px-3 mb-2" style="font-size: 0.875rem;">' +
                    '<strong>AI answer:</strong> <span class="text-muted">Not extracted yet</span>' +
                    '</div>';
            }

            // Determine input type based on value field (data.value descreve o tipo esperado)
            if (data.value && (data.value.toLowerCase().includes('yes/no') || data.value.toLowerCase().includes('yes') || data.value.toLowerCase().includes('no'))) {
                html += '<select id="' + fieldId + '" name="extraction_data[' + data.id + '][value]" class="form-select form-select-sm">' +
                    '<option value="">N/A</option>' +
                    '<option value="Yes"' + (value === 'Yes' ? ' selected' : '') + '>Yes</option>' +
                    '<option value="No"' + (value === 'No' ? ' selected' : '') + '>No</option>' +
                    '</select>';
            } else if (data.value && data.value.toLowerCase().includes('number')) {
                html += '<input type="number" id="' + fieldId + '" name="extraction_data[' + data.id + '][value]" class="form-control form-control-sm" value="' + (value || '') + '">';
            } else {
                html += '<textarea id="' + fieldId + '" name="extraction_data[' + data.id + '][value]" class="form-control form-control-sm" rows="2">' + (value || '') + '</textarea>';
            }

            html += '<input type="hidden" name="extraction_data[' + data.id + '][extraction_data_id]" value="' + data.id + '">' +
                '</div>';
        });

        container.innerHTML = html;
    }

    function updateExtractionNavigationButtons() {
        const prevBtn = document.querySelector('button[onclick="navigateStudyExtraction(\'prev\')"]');
        const saveAndNextBtn = document.querySelector('button[onclick="saveAndNextExtraction()"]');
        const nextBtn = document.querySelector('button[onclick="navigateStudyExtraction(\'next\')"]');

        if (allStudiesForNavigation.length === 0) {
            if (prevBtn) prevBtn.disabled = true;
            if (saveAndNextBtn) saveAndNextBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = true;
            return;
        }

        const totalRows = allStudiesForNavigation.length;
        const isFirst = currentStudyRowIndex <= 0;
        const isLast = currentStudyRowIndex >= totalRows - 1 || currentStudyRowIndex < 0;

        if (prevBtn) prevBtn.disabled = isFirst;
        if (saveAndNextBtn) saveAndNextBtn.disabled = isLast;
        if (nextBtn) nextBtn.disabled = isLast;
    }

    window.navigateStudyExtraction = function(direction) {
        if (allStudiesForNavigation.length === 0) return;

        const totalRows = allStudiesForNavigation.length;
        let newIndex = currentStudyRowIndex;

        if (direction === 'prev' && currentStudyRowIndex > 0) {
            newIndex = currentStudyRowIndex - 1;
        } else if (direction === 'next' && currentStudyRowIndex < totalRows - 1) {
            newIndex = currentStudyRowIndex + 1;
        } else {
            return;
        }

        const newStudy = allStudiesForNavigation[newIndex];
        if (newStudy) {
            currentStudyData = newStudy;
            currentStudyRowIndex = newIndex;
            document.getElementById('study_extraction_id').value = newStudy.id;
            document.getElementById('studyExtractionDataModalTitle').textContent = 'Extraction Data - ' + (newStudy.custom_id || newStudy.id);
            
            // Load PDF preview
            const pdfPreview = document.getElementById('study-pdf-preview-extraction');
            if (newStudy.pdf_path) {
                const pdfUrl = '/storage/s3/' + encodeURIComponent(newStudy.pdf_path);
                pdfPreview.style.padding = '0';
                pdfPreview.style.overflow = 'hidden';
                pdfPreview.style.backgroundColor = 'transparent';
                pdfPreview.innerHTML = '<embed src="' + pdfUrl + '#toolbar=0&view=FitH" type="application/pdf" style="width: 100%; height: 100%; border: none; flex: 1; min-height: 0;" />';
            } else {
                pdfPreview.style.padding = '0';
                pdfPreview.style.backgroundColor = '#f5f6f8';
                pdfPreview.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 w-100"><div class="text-center text-muted p-5"><i class="icon-base ri ri-file-pdf-line icon-3x mb-3"></i><p class="mb-0">No PDF available for this study</p></div></div>';
            }
            
            populateExtractionDataFields(newStudy);
            populateAIInputOutput(newStudy);
            updateExtractionNavigationButtons();
        }
    };

    window.saveAndNextExtraction = function() {
        saveExtractionData().then(function() {
            navigateStudyExtraction('next');
        });
    };

    function saveExtractionData() {
        const studyId = document.getElementById('study_extraction_id').value;
        if (!studyId) {
            return Promise.reject('No study selected');
        }

        const formData = new FormData(document.getElementById('studyExtractionDataForm'));
        const extractionDataArray = [];

        // Collect all extraction data values
        extractionData.forEach(function(data) {
            const fieldId = 'extraction_data_' + data.id;
            const field = document.getElementById(fieldId);
            if (field) {
                extractionDataArray.push({
                    extraction_data_id: data.id,
                    value: field.value || null
                });
            }
        });

        return fetch('/projects/' + projectId + '/extractions/' + extractionId + '/studies/' + studyId + '/extraction-data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                extraction_data: extractionDataArray
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update study data with saved values
                if (currentStudyData) {
                    const savedValues = {};
                    extractionDataArray.forEach(function(item) {
                        if (item.value) {
                            savedValues[item.extraction_data_id] = item.value;
                        }
                    });
                    currentStudyData.extraction_data_values = savedValues;
                    currentStudyData.extracted = true;
                    const statusCell = document.querySelector('[data-extraction-status="' + currentStudyData.id + '"]');
                    if (statusCell) statusCell.outerHTML = extractionStatusIcon(currentStudyData);
                }
                extractionSavedSinceOpen = true;

                if (typeof window.showToast === 'function') {
                    window.showToast(data.message || 'Extraction data saved successfully', 'success');
                } else {
                    console.log(data.message || 'Extraction data saved successfully');
                }
                return data;
            } else {
                throw new Error(data.message || 'Error saving extraction data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Error saving extraction data: ' + error.message, 'error');
            } else {
                alert('Error saving extraction data: ' + error.message);
            }
            throw error;
        });
    }

    // Handle form submission
    const studyExtractionDataForm = document.getElementById('studyExtractionDataForm');
    if (studyExtractionDataForm) {
        studyExtractionDataForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveExtractionData().then(function() {
                const modal = bootstrap.Modal.getInstance(document.getElementById('studyExtractionDataModal'));
                modal.hide();
            });
        });
    }

    // Reprocess extraction function
    window.reprocessExtraction = function() {
        if (!confirm('Are you sure you want to reprocess this extraction? This will reprocess all studies.')) {
            return;
        }

        const button = document.querySelector('button[onclick="reprocessExtraction()"]');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="icon-base ri ri-loader-4-line me-1"></i>Processing...';
        }

        fetch('/projects/' + projectId + '/extractions/' + extractionId + '/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof window.showToast === 'function') {
                    window.showToast(data.message || 'Processing started successfully', 'success');
                } else {
                    alert(data.message || 'Processing started successfully');
                }
            } else {
                throw new Error(data.message || 'Error starting processing');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Error starting processing: ' + error.message, 'error');
            } else {
                alert('Error starting processing: ' + error.message);
            }
        })
        .finally(() => {
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="icon-base ri ri-refresh-line me-1"></i>Reprocess';
            }
        });
    };

    // Continue the extraction with only the studies that have no AI output yet
    const processPendingBtn = document.getElementById('processPendingStudiesBtn');
    if (processPendingBtn) {
        processPendingBtn.addEventListener('click', function() {
            if (!confirm('This will process with AI only the studies that have no result yet. Continue?')) {
                return;
            }

            const originalText = processPendingBtn.innerHTML;
            processPendingBtn.disabled = true;
            processPendingBtn.innerHTML = '<i class="icon-base ri ri-loader-4-line me-1"></i>Processing...';
            const notify = (message, type) => typeof window.showToast === 'function'
                ? window.showToast(message, type)
                : alert(message);

            fetch('/projects/' + projectId + '/extractions/' + extractionId + '/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ pending_only: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notify(data.message || 'Processing started successfully!', 'success');
                    // Reload the page after a short delay to see updated statuses
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    notify(data.message || 'Error starting processing.', 'error');
                    processPendingBtn.disabled = false;
                    processPendingBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error processing pending studies:', error);
                notify('Error starting processing. Please try again.', 'error');
                processPendingBtn.disabled = false;
                processPendingBtn.innerHTML = originalText;
            });
        });
    }

    // Ensure jQuery and DataTables are loaded before initializing
    ensureJQuery()
        .then(() => ensureDataTables())
        .then(() => {
            console.log('jQuery and DataTables loaded, initializing table...');
            initStudiesTable();
        })
        .catch(error => {
            console.error('Error loading scripts:', error);
            // Fallback: try again after a delay
            setTimeout(() => {
                if (window.jQuery && jQuery.fn && (jQuery.fn.DataTable || jQuery.fn.dataTable)) {
                    console.log('DataTables available after delay, initializing...');
                    initStudiesTable();
                } else {
                    console.error('Failed to load DataTables after retry');
                }
            }, 1000);
        });
});
</script>
@endsection

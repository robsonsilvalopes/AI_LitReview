@extends('layouts/contentNavbarLayout')

@section('title', $finalScreening->nome . ' - Final Screening')

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
                <h5 class="card-title mb-0">{{ $finalScreening->nome }}</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('projects.final', $project) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="icon-base ri ri-arrow-left-line me-1"></i>Back
                    </a>
                    @if(in_array($finalScreening->tipo, ['AI OpenRouter', 'AI Local']))
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
                        <p class="mb-0">{{ $finalScreening->tipo }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Date</h6>
                        <p class="mb-0">{{ $finalScreening->data->format('Y-m-d') }}</p>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Responsible</h6>
                        <p class="mb-0">{{ $finalScreening->responsavel->name }} ({{ $finalScreening->responsavel->email }})</p>
                    </div>
                    @if($finalScreening->aiModel)
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">AI Model</h6>
                        <p class="mb-0">{{ $finalScreening->aiModel->name }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@include('content.projects.partials.screening-status-chart', ['statusSummary' => $statusSummary])

@if($finalScreening->tipo === 'Human')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Studies</h5>
                <a href="{{ route('final-screenings.download-studies-xls', [$project, $finalScreening]) }}" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-file-excel-2-line me-1"></i>Download XLS
                </a>
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
                                <th>Status</th>
                                <th>Inclusion Criteria</th>
                                <th>Exclusion Criteria</th>
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
@elseif(in_array($finalScreening->tipo, ['AI OpenRouter', 'AI Local']))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Studies</h5>
                <a href="{{ route('final-screenings.download-studies-xls', [$project, $finalScreening]) }}" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-file-excel-2-line me-1"></i>Download XLS
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="studiesTableAI" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>DOI</th>
                                <th>Abstract</th>
                                <th>Status</th>
                                <th>Metadata</th>
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
@endif

@endsection

@if($finalScreening->tipo === 'Human')
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

    function initDataTable() {
        if (typeof jQuery === 'undefined') {
            console.error('jQuery is still not available!');
            return;
        }
        if (typeof jQuery.fn.DataTable === 'undefined' && typeof jQuery.fn.dataTable === 'undefined') {
            console.error('DataTables is not loaded!');
            return;
        }

        // Global variables
        const projectId = {{ $project->id }};
        const finalScreeningId = {{ $finalScreening->id }};
        let studiesTable = null;
        let allStudiesForNavigation = [];
        let currentStudyData = null;
        let currentStudyRowIndex = -1;
        let projectInclusionCriteria = [];
        let projectExclusionCriteria = [];
        let selectedStatus = null;

    // Initialize DataTable
    const $table = jQuery('#studiesTable');
    if ($table.length === 0) {
        console.error('Table element not found');
        return;
    }
    
    studiesTable = $table.DataTable({
        processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: '/projects/' + projectId + '/studies',
                type: 'GET',
                data: function(d) {
                    d.filter_initial_included = true;
                    d.criteria_type = 'final';
                    d.screening_id = finalScreeningId;
                    return d;
                },
                headers: {
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                dataSrc: function(json) {
                    if (!json.data || !Array.isArray(json.data)) {
                        return [];
                    }
                    return json.data;
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTables AJAX error:', error, thrown);
                }
            },
            columns: [
                { data: 'custom_id', name: 'custom_id', orderable: true, searchable: true },
                { data: 'name', name: 'name', orderable: true, searchable: true },
                { data: 'doi', name: 'doi', orderable: true, searchable: true },
                { data: 'abstract', name: 'abstract', orderable: false, searchable: true },
                { data: 'status_selecao_final', name: 'status_selecao_final', orderable: true, searchable: false },
                { data: 'inclusion_criteria_display', name: 'inclusion_criteria_display', orderable: false, searchable: false },
                { data: 'exclusion_criteria_display', name: 'exclusion_criteria_display', orderable: false, searchable: false }
            ],
            columnDefs: [
                {
                    targets: 0,
                    render: function(data, type, row) {
                        return '<div class="d-flex align-items-center"><span>' + (data || 'N/A') + '</span></div>';
                    }
                },
                {
                    targets: 1,
                    render: function(data, type, row) {
                        const title = data || 'N/A';
                        return '<a href="javascript:void(0);" onclick="openStudyViewModal(' + row.id + ')" class="text-primary fw-semibold" style="cursor: pointer; text-decoration: none;">' + title + '</a>';
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
                        const abstractValue = row && row.abstract ? row.abstract : data;
                        if (!abstractValue) {
                            return '<span class="text-muted">No abstract</span>';
                        }
                        const plainText = String(abstractValue).replace(/<[^>]*>/g, '').trim();
                        const words = plainText.split(/\s+/);
                        const text = words.slice(0, 20).join(' ') + (words.length > 20 ? '...' : '');
                        return '<span class="d-inline-block" style="max-width: 420px; white-space: normal;">' + text + '</span>';
                    }
                },
                {
                    targets: 4,
                    render: function(data, type, row) {
                        if (data === true) return '<span class="badge rounded-pill bg-success">Included</span>';
                        if (data === false) return '<span class="badge rounded-pill bg-danger">Excluded</span>';
                        return '<span class="badge rounded-pill bg-secondary">N/A</span>';
                    }
                },
                {
                    targets: 5,
                    render: function(data, type, row) {
                        if (!data || !Array.isArray(data) || data.length === 0) {
                            return '<span class="text-muted">-</span>';
                        }
                        return data.map(function(name) {
                            return '<span class="badge bg-success-subtle text-success me-1 mb-1">' + name + '</span>';
                        }).join('');
                    }
                },
                {
                    targets: 6,
                    render: function(data, type, row) {
                        if (!data || !Array.isArray(data) || data.length === 0) {
                            return '<span class="text-muted">-</span>';
                        }
                        return data.map(function(name) {
                            return '<span class="badge bg-danger-subtle text-danger me-1 mb-1">' + name + '</span>';
                        }).join('');
                    }
                }
            ],
            order: [[0, 'asc']],
            pageLength: 15,
            lengthMenu: [[10, 15, 25, 50, 100], [10, 15, 25, 50, 100]]
        });

        // Store data for navigation
        studiesTable.on('xhr.dt', function(e, settings, json) {
            allStudiesForNavigation = json.data || [];
        });

    window.openStudyViewModal = function(studyId) {
        // Find study in DataTable
        let study = allStudiesForNavigation.find(s => s.id === studyId);
        let rowIndex = -1;
        
        if (!study && studiesTable) {
            studiesTable.rows().every(function() {
                const rowData = this.data();
                if (rowData.id === studyId) {
                    study = rowData;
                    rowIndex = this.index();
                    return false;
                }
            });
        } else {
            rowIndex = allStudiesForNavigation.findIndex(s => s.id === studyId);
        }

        if (!study) {
            if (typeof window.showToast === 'function') {
                window.showToast('Study not found', 'error');
            } else {
                alert('Study not found');
            }
            return;
        }

        displayStudyInModal(study, rowIndex);
    };

    function displayStudyInModal(study, rowIndex = -1) {
        currentStudyData = study;
        currentStudyRowIndex = rowIndex;
        document.getElementById('study_view_id').value = study.id;
        
        updateNavigationButtons();
        document.getElementById('study-view-custom-id').textContent = study.custom_id || 'N/A';
        document.getElementById('study-view-name').textContent = study.name || 'N/A';
        
        // DOI
        const doiElement = document.getElementById('study-view-doi');
        if (study.doi) {
            const doiUrl = 'https://doi.org/' + encodeURIComponent(study.doi.trim());
            doiElement.innerHTML = '<a href="' + doiUrl + '" target="_blank" rel="noopener" class="text-primary">' + study.doi + '</a>';
        } else {
            doiElement.textContent = 'N/A';
        }
        
        // Load PDF preview
        const pdfPreview = document.getElementById('study-pdf-preview');
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
        
        // Reset status selection
        resetStatusSelection();
        
        // Load project criteria first, then set status
        loadProjectCriteria().then(() => {
            if (study.status_selecao_final === true) {
                selectStudyStatus('include', true);
            } else if (study.status_selecao_final === false) {
                selectStudyStatus('exclude', true);
            }
        });
        
        const modalElement = document.getElementById('studyViewModal');
        const modal = new bootstrap.Modal(modalElement);
        
        // Remove backdrop when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function() {
            // Remove any remaining backdrop
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            // Remove modal-open class from body
            document.body.classList.remove('modal-open');
            // Reset body padding
            document.body.style.paddingRight = '';
        });
        
        modal.show();
    }

    function updateNavigationButtons() {
        const prevBtn = document.getElementById('prevStudyBtn');
        const saveAndNextBtn = document.getElementById('saveAndNextBtn');
        const nextBtn = document.getElementById('nextStudyBtn');
        
        if (allStudiesForNavigation.length === 0) {
            prevBtn.disabled = true;
            saveAndNextBtn.disabled = true;
            nextBtn.disabled = true;
            return;
        }
        
        const totalRows = allStudiesForNavigation.length;
        const isFirst = currentStudyRowIndex <= 0;
        const isLast = currentStudyRowIndex >= totalRows - 1 || currentStudyRowIndex < 0;
        
        prevBtn.disabled = isFirst;
        saveAndNextBtn.disabled = isLast;
        nextBtn.disabled = isLast;
    }

    window.navigateStudy = function(direction) {
        if (allStudiesForNavigation.length === 0) return;
        
        const totalRows = allStudiesForNavigation.length;
        let newIndex = currentStudyRowIndex;
        if (direction === 'next' && currentStudyRowIndex < totalRows - 1) {
            newIndex = currentStudyRowIndex + 1;
        } else if (direction === 'prev' && currentStudyRowIndex > 0) {
            newIndex = currentStudyRowIndex - 1;
        }
        
        if (newIndex !== currentStudyRowIndex && newIndex >= 0 && newIndex < totalRows) {
            displayStudyInModal(allStudiesForNavigation[newIndex], newIndex);
        }
    };

    function resetStatusSelection() {
        selectedStatus = null;
        document.getElementById('includeCard').style.border = '2px solid transparent';
        document.getElementById('excludeCard').style.border = '2px solid transparent';
        document.getElementById('criteriaSelectionCard').style.display = 'none';
    }

    window.selectStudyStatus = function(status, loadCriteria = false) {
        selectedStatus = status;
        
        if (status === 'include') {
            document.getElementById('includeCard').style.border = '2px solid #28a745';
            document.getElementById('excludeCard').style.border = '2px solid transparent';
            document.getElementById('criteriaCardTitle').textContent = 'Inclusion Criteria';
        } else {
            document.getElementById('excludeCard').style.border = '2px solid #dc3545';
            document.getElementById('includeCard').style.border = '2px solid transparent';
            document.getElementById('criteriaCardTitle').textContent = 'Exclusion Criteria';
        }
        
        const criteriaCard = document.getElementById('criteriaSelectionCard');
        if (criteriaCard) {
            criteriaCard.style.display = 'block';
        }
        
        // Always ensure criteria are loaded before populating (exactly like initial screening)
        // Load criteria if not already loaded
        if (projectInclusionCriteria.length === 0 || projectExclusionCriteria.length === 0) {
            loadProjectCriteria().then(() => {
                populateCriteriaList(status);
            }).catch(error => {
                console.error('Error loading criteria:', error);
                populateCriteriaList(status);
            });
        } else {
            populateCriteriaList(status);
        }
    };

    function loadProjectCriteria() {
        const inclusionPromise = fetch('/projects/' + projectId + '/inclusion-criterias', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            projectInclusionCriteria = data.data || data || [];
        })
        .catch(error => console.error('Error loading inclusion criteria:', error));
        
        const exclusionPromise = fetch('/projects/' + projectId + '/exclusion-criterias', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            projectExclusionCriteria = data.data || data || [];
        })
        .catch(error => console.error('Error loading exclusion criteria:', error));
        
        return Promise.all([inclusionPromise, exclusionPromise]);
    }

    function populateCriteriaList(status) {
        const criteriaList = document.getElementById('criteriaList');
        if (!criteriaList) {
            console.error('criteriaList element not found');
            return;
        }
        
        const criteria = status === 'include' ? projectInclusionCriteria : projectExclusionCriteria;
        // Use inclusion_criteria_ids/exclusion_criteria_ids if available (from screening), otherwise fall back to inclusion_criteria_final/exclusion_criteria_final
        const selectedCriteriaIds = status === 'include' 
            ? (currentStudyData?.inclusion_criteria_ids || currentStudyData?.inclusion_criteria_final || [])
            : (currentStudyData?.exclusion_criteria_ids || currentStudyData?.exclusion_criteria_final || []);
        
        if (!criteria || criteria.length === 0) {
            criteriaList.innerHTML = '<p class="text-muted small mb-0">No criteria available</p>';
            return;
        }
        
        let html = '';
        criteria.forEach(function(item) {
            const criteriaId = item.custom_id || item.id;
            const criteriaDesc = status === 'include' ? item.inclusion_criteria : item.exclusion_criteria;
            // Convert both to numbers for comparison
            const itemIdNum = parseInt(item.id);
            const isChecked = selectedCriteriaIds.some(id => parseInt(id) === itemIdNum) ? 'checked' : '';
            
            html += '<div class="form-check mb-2">' +
                '<input class="form-check-input criteria-checkbox" type="checkbox" value="' + item.id + '" id="criteria_' + item.id + '" ' + isChecked + '>' +
                '<label class="form-check-label small" for="criteria_' + item.id + '">' +
                    '<strong>' + criteriaId + '</strong> - ' + (criteriaDesc || 'N/A') +
                '</label>' +
            '</div>';
        });
        
        criteriaList.innerHTML = html;
    }

    window.saveAndNext = function() {
        const studyId = document.getElementById('study_view_id').value;
        if (!studyId) return;
        
        const selectedCriteriaIds = [];
        document.querySelectorAll('.criteria-checkbox:checked').forEach(function(checkbox) {
            selectedCriteriaIds.push(parseInt(checkbox.value));
        });
        
        const saveAndNextBtn = document.getElementById('saveAndNextBtn');
        const originalText = saveAndNextBtn.innerHTML;
        saveAndNextBtn.disabled = true;
        saveAndNextBtn.innerHTML = '<i class="icon-base ri ri-loader-4-line"></i> Saving...';
        
        fetch('/projects/' + projectId + '/studies/' + studyId + '/screening-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                status: selectedStatus,
                criteria_ids: selectedCriteriaIds,
                screening_id: finalScreeningId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const updatedStatus = selectedStatus === 'include' ? true : (selectedStatus === 'exclude' ? false : null);
                if (allStudiesForNavigation[currentStudyRowIndex]) {
                    allStudiesForNavigation[currentStudyRowIndex].status_selecao_final = updatedStatus;
                    allStudiesForNavigation[currentStudyRowIndex].inclusion_criteria_final = selectedStatus === 'include' ? selectedCriteriaIds : [];
                    allStudiesForNavigation[currentStudyRowIndex].exclusion_criteria_final = selectedStatus === 'exclude' ? selectedCriteriaIds : [];
                }
                if (currentStudyData) {
                    currentStudyData.status_selecao_final = updatedStatus;
                    currentStudyData.inclusion_criteria_final = selectedStatus === 'include' ? selectedCriteriaIds : [];
                    currentStudyData.exclusion_criteria_final = selectedStatus === 'exclude' ? selectedCriteriaIds : [];
                }
                navigateStudy('next');
                if (studiesTable) {
                    studiesTable.ajax.reload(null, false);
                }
                if (typeof window.showToast === 'function') {
                    window.showToast('Study updated successfully!', 'success');
                } else {
                    alert('Study updated successfully!');
                }
            } else {
                if (typeof window.showToast === 'function') {
                    window.showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Error saving study. Please try again.', 'error');
            } else {
                alert('Error saving study. Please try again.');
            }
        })
        .finally(() => {
            saveAndNextBtn.disabled = false;
            saveAndNextBtn.innerHTML = originalText;
            updateNavigationButtons();
        });
    };

    // Save button handler
    document.getElementById('saveStudyViewBtn').addEventListener('click', function() {
        const studyId = document.getElementById('study_view_id').value;
        if (!studyId) return;
        
        const selectedCriteriaIds = [];
        document.querySelectorAll('.criteria-checkbox:checked').forEach(function(checkbox) {
            selectedCriteriaIds.push(parseInt(checkbox.value));
        });
        
        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="icon-base ri ri-loader-4-line"></i> Saving...';
        
        fetch('/projects/' + projectId + '/studies/' + studyId + '/screening-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                status: selectedStatus,
                criteria_ids: selectedCriteriaIds,
                screening_id: finalScreeningId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const updatedStatus = selectedStatus === 'include' ? true : (selectedStatus === 'exclude' ? false : null);
                if (allStudiesForNavigation[currentStudyRowIndex]) {
                    allStudiesForNavigation[currentStudyRowIndex].status_selecao_final = updatedStatus;
                    allStudiesForNavigation[currentStudyRowIndex].inclusion_criteria_final = selectedStatus === 'include' ? selectedCriteriaIds : [];
                    allStudiesForNavigation[currentStudyRowIndex].exclusion_criteria_final = selectedStatus === 'exclude' ? selectedCriteriaIds : [];
                }
                if (studiesTable) {
                    studiesTable.ajax.reload(null, false);
                }
                if (typeof window.showToast === 'function') {
                    window.showToast('Study updated successfully!', 'success');
                } else {
                    alert('Study updated successfully!');
                }
            } else {
                if (typeof window.showToast === 'function') {
                    window.showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Error saving study. Please try again.', 'error');
            } else {
                alert('Error saving study. Please try again.');
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
    } // End of initDataTable function

    ensureJQuery()
        .then(ensureDataTables)
        .then(initDataTable)
        .catch((error) => {
            console.error('Failed to load jQuery/DataTables:', error);
        });
});
</script>
@endsection
@elseif(in_array($finalScreening->tipo, ['AI OpenRouter', 'AI Local']))
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/jquery/jquery.min.js') }}"></script>
@endsection

@section('page-script')
<script>
// Readable table of the criteria scores in a raw AI response. Returns false when it has none.
window.renderAiCriteria = (function() {
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function parseAnswer(raw) {
        let content = raw;
        try {
            const envelope = JSON.parse(String(raw).trim());
            if (envelope && envelope.choices && envelope.choices[0] && envelope.choices[0].message) {
                content = envelope.choices[0].message.content;
            } else if (envelope && envelope.message && envelope.message.content !== undefined) {
                content = envelope.message.content;
            } else if (envelope && Array.isArray(envelope.criteria)) {
                return envelope;
            }
        } catch (e) { /* not an envelope: the value may already be the model text */ }

        const text = String(content ?? '');
        const start = text.indexOf('{'), end = text.lastIndexOf('}');
        if (start === -1 || end <= start) return null;
        try {
            return JSON.parse(text.substring(start, end + 1));
        } catch (e) {
            return null;
        }
    }

    return function(raw, container) {
        container.innerHTML = '';
        const answer = raw ? parseAnswer(raw) : null;
        const entries = answer && Array.isArray(answer.criteria)
            ? answer.criteria.filter(c => c && typeof c === 'object' && c.id !== undefined && c.id !== null)
            : [];
        if (!entries.length) return false;

        const responsive = el('div', 'table-responsive');
        const table = el('table', 'table table-sm table-bordered align-top mb-0 small');
        const thead = el('thead', 'table-light');
        const headRow = el('tr');
        ['Criterion', 'Score', 'Justification'].forEach(label => headRow.appendChild(el('th', '', label)));
        thead.appendChild(headRow);
        table.appendChild(thead);

        const tbody = el('tbody');
        entries.forEach(entry => {
            const row = el('tr');
            row.appendChild(el('td', 'fw-semibold text-nowrap', String(entry.id).trim().toUpperCase()));
            row.appendChild(el('td', 'text-center', entry.score ?? '-'));
            const justification = el('td', '', entry.justification || '-');
            justification.style.whiteSpace = 'pre-wrap';
            row.appendChild(justification);
            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        responsive.appendChild(table);
        container.appendChild(responsive);
        return true;
    };
})();
</script>
<script>
// Initialize DataTables after page loads for AI screenings
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

    // Global variables (declared in DOMContentLoaded scope to be accessible by all functions)
    const projectId = {{ $project->id }};
    const finalScreeningId = {{ $finalScreening->id }};
    let currentStudyDataAI = null;
    let currentStudyRowIndexAI = -1;
    let allStudiesForNavigationAI = [];
    let selectedStatusAI = null;

    function initDataTable() {
        if (typeof jQuery === 'undefined') {
            console.error('jQuery is still not available!');
            return;
        }
        if (typeof jQuery.fn.DataTable === 'undefined' && typeof jQuery.fn.dataTable === 'undefined') {
            console.error('DataTables is not loaded!');
            return;
        }

        const $table = jQuery('#studiesTableAI');

        if (!$table.length) {
            console.error('Table element not found!');
            return;
        }

        window.studiesTableAI = $table.DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: '/projects/' + projectId + '/studies',
                type: 'GET',
                data: function(d) {
                    d.filter_initial_included = true;
                    d.criteria_type = 'final';
                    d.screening_id = finalScreeningId;
                    return d;
                },
                headers: {
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                dataSrc: function(json) {
                    if (!json.data || !Array.isArray(json.data)) {
                        return [];
                    }
                    // Debug: log the status value
                    if (json.data.length > 0) {
                        console.log('DEBUG AI: status_selecao_final value:', json.data[0].status_selecao_final, 'type:', typeof json.data[0].status_selecao_final, 'full row:', json.data[0]);
                    }
                    return json.data;
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTables AJAX error:', error, thrown);
                }
            },
            columns: [
                { data: 'custom_id', name: 'custom_id', orderable: true, searchable: true },
                { data: 'name', name: 'name', orderable: true, searchable: true },
                { data: 'doi', name: 'doi', orderable: true, searchable: true },
                { data: 'abstract', name: 'abstract', orderable: false, searchable: true },
                { data: 'status_selecao_final', name: 'status_selecao_final', orderable: true, searchable: false },
                { data: 'screening_metadata', name: 'screening_metadata', orderable: false, searchable: false, width: '200px' }
            ],
            columnDefs: [
                {
                    targets: 0,
                    render: function(data, type, row) {
                        return '<div class="d-flex align-items-center"><span>' + (data || 'N/A') + '</span></div>';
                    }
                },
                {
                    targets: 1,
                    render: function(data, type, row) {
                        const title = data || 'N/A';
                        return '<a href="javascript:void(0);" onclick="openStudyViewModalAI(' + row.id + ')" class="text-primary fw-semibold" style="cursor: pointer; text-decoration: none;">' + title + '</a>';
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
                        const abstractValue = row && row.abstract ? row.abstract : data;
                        if (!abstractValue) {
                            return '<span class="text-muted">No abstract</span>';
                        }
                        const plainText = String(abstractValue).replace(/<[^>]*>/g, '').trim();
                        const words = plainText.split(/\s+/);
                        const text = words.slice(0, 20).join(' ') + (words.length > 20 ? '...' : '');
                        return '<span class="d-inline-block" style="max-width: 420px; white-space: normal;">' + text + '</span>';
                    }
                },
                {
                    targets: 4,
                    render: function(data, type, row) {
                        console.log('DEBUG AI render: data=', data, 'type=', typeof data, 'row.id=', row.id);
                        if (data === true || data === 1) return '<span class="badge rounded-pill bg-success">Included</span>';
                        if (data === false || data === 0) return '<span class="badge rounded-pill bg-danger">Excluded</span>';
                        return '<span class="badge rounded-pill bg-secondary">N/A</span>';
                    }
                },
                {
                    targets: 5,
                    render: function(data, type, row) {
                        if (!data || typeof data !== 'object') {
                            return '<span class="text-muted">-</span>';
                        }
                        const metadata = data;
                        const lines = [];
                        if (metadata && metadata.total_duration !== null && metadata.total_duration !== undefined) {
                            lines.push('<i class="icon-base ri ri-time-line me-1" aria-label="Duration" title="Duration"></i>' + (metadata.total_duration / 1000).toFixed(2) + 's');
                        }
                        if (metadata && metadata.input_tokens !== null && metadata.input_tokens !== undefined) {
                            lines.push('<i class="icon-base ri ri-arrow-up-line me-1" aria-label="Input Tokens" title="Input Tokens"></i>' + metadata.input_tokens);
                        }
                        if (metadata && metadata.output_tokens !== null && metadata.output_tokens !== undefined) {
                            lines.push('<i class="icon-base ri ri-arrow-down-line me-1" aria-label="Output Tokens" title="Output Tokens"></i>' + metadata.output_tokens);
                        }
                        if (metadata && metadata.total_tokens !== null && metadata.total_tokens !== undefined) {
                            lines.push('<i class="icon-base ri ri-add-line me-1" aria-label="Total Tokens" title="Total Tokens"></i>' + metadata.total_tokens);
                        }
                        if (metadata && metadata.cost !== null && metadata.cost !== undefined) {
                            lines.push('<i class="icon-base ri ri-money-dollar-circle-line me-1" aria-label="Cost" title="Cost"></i>$' + parseFloat(metadata.cost).toFixed(5));
                        }
                        if (lines.length === 0) {
                            return '<span class="text-muted">-</span>';
                        }
                        return '<div style="font-size: 0.875rem; line-height: 1.8; min-width: 150px;">' + lines.join('<br>') + '</div>';
                    }
                }
            ],
            order: [[0, 'asc']],
            pageLength: 15,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: {
                search: "Search:",
                lengthMenu: "_MENU_",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)",
                paginate: {
                    first: '<i class="icon-base ri ri-arrow-left-s-line"></i>',
                    previous: '<i class="icon-base ri ri-arrow-left-s-line"></i>',
                    next: '<i class="icon-base ri ri-arrow-right-s-line"></i>',
                    last: '<i class="icon-base ri ri-arrow-right-s-line"></i>'
                }
            }
        });
        
        // Store data for navigation (same as Human modal)
        window.studiesTableAI.on('xhr.dt', function(e, settings, json) {
            allStudiesForNavigationAI = json.data || [];
        });
    }

    ensureJQuery()
        .then(() => ensureDataTables())
        .then(() => {
            setTimeout(() => {
                initDataTable();
            }, 100);
        })
        .catch((error) => {
            console.error('Failed to load jQuery/DataTables:', error);
        });

    // Function to open AI study modal (replicated from Human modal logic)
    window.openStudyViewModalAI = function(studyId) {
        // Find study in DataTable (same logic as Human modal)
        let study = allStudiesForNavigationAI.find(s => s.id === studyId);
        let rowIndex = -1;
        
        if (!study && window.studiesTableAI) {
            window.studiesTableAI.rows().every(function() {
                const rowData = this.data();
                if (rowData.id === studyId) {
                    study = rowData;
                    rowIndex = this.index();
                    return false;
                }
            });
        } else {
            rowIndex = allStudiesForNavigationAI.findIndex(s => s.id === studyId);
        }

        if (!study) {
            if (typeof window.showToast === 'function') {
                window.showToast('Study not found', 'error');
            } else {
                alert('Study not found');
            }
            return;
        }

        loadStudyDataAI(study, rowIndex);
    };

    function loadStudyDataAI(study, rowIndex = -1) {
        currentStudyDataAI = study;
        currentStudyRowIndexAI = rowIndex;
        
        // Set study data
        document.getElementById('study_view_ai_id').value = study.id;
        document.getElementById('study-view-ai-custom-id').textContent = study.custom_id || 'N/A';
        document.getElementById('study-view-ai-name').textContent = study.name || 'N/A';
        
        // DOI
        const doiElement = document.getElementById('study-view-ai-doi');
        if (study.doi) {
            const doiUrl = 'https://doi.org/' + encodeURIComponent(study.doi.trim());
            doiElement.innerHTML = '<a href="' + doiUrl + '" target="_blank" rel="noopener" class="text-primary">' + study.doi + '</a>';
        } else {
            doiElement.textContent = 'N/A';
        }
        
        // Set AI input and output
        const inputAi = study.input_ai || 'No AI input available';
        let outputAi = study.output_ai || 'No AI output available';
        
        // Process output_ai: remove leading whitespace and prettify JSON if possible
        if (outputAi && outputAi !== 'No AI output available') {
            // Remove leading whitespace (spaces, tabs, newlines) - trim from start only
            outputAi = outputAi.replace(/^\s+/, '');
            
            // Try to parse and prettify JSON
            try {
                // First, try to parse the entire string as JSON
                const jsonData = JSON.parse(outputAi);
                // Format JSON with 2-space indentation
                outputAi = JSON.stringify(jsonData, null, 2);
            } catch (e) {
                // If entire string is not valid JSON, try to find and format JSON object within
                try {
                    // Look for JSON object pattern (handles nested objects)
                    const jsonMatch = outputAi.match(/\{[\s\S]*\}/);
                    if (jsonMatch && jsonMatch[0]) {
                        const jsonStr = jsonMatch[0];
                        const jsonData = JSON.parse(jsonStr);
                        const formattedJson = JSON.stringify(jsonData, null, 2);
                        // Replace the JSON part with formatted version, keeping any text before/after
                        const beforeJson = outputAi.substring(0, jsonMatch.index);
                        const afterJson = outputAi.substring(jsonMatch.index + jsonMatch[0].length);
                        outputAi = beforeJson + formattedJson + afterJson;
                    }
                } catch (e2) {
                    // If all parsing fails, just use the trimmed version (whitespace removed)
                    // The output will be displayed as-is but without leading whitespace
                }
            }
        }
        
        document.getElementById('study-view-ai-input').textContent = inputAi;
        document.getElementById('study-view-ai-output').textContent = outputAi;

        // Readable criteria table; responses without criteria fall back to the raw view
        const hasCriteria = window.renderAiCriteria(
            study.output_ai,
            document.getElementById('study-view-ai-criteria')
        );
        document.getElementById('study-view-ai-criteria-item').classList.toggle('d-none', !hasCriteria);
        bootstrap.Tab.getOrCreateInstance(document.getElementById(
            hasCriteria ? 'study-view-ai-criteria-tab' : 'study-view-ai-raw-tab'
        )).show();
        
        // Load PDF preview
        const pdfPreview = document.getElementById('study-pdf-preview-ai');
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
        
        // Reset and set status (replicated from Human modal, but without criteria)
        selectedStatusAI = null;
        const includeCard = document.getElementById('includeCardAI');
        const excludeCard = document.getElementById('excludeCardAI');
        includeCard.style.border = '2px solid transparent';
        excludeCard.style.border = '2px solid transparent';
        
        if (study.status_selecao_final === true) {
            selectedStatusAI = 'include';
            includeCard.style.border = '2px solid #28a745';
            excludeCard.style.border = '2px solid transparent';
        } else if (study.status_selecao_final === false) {
            selectedStatusAI = 'exclude';
            includeCard.style.border = '2px solid transparent';
            excludeCard.style.border = '2px solid #dc3545';
        }
        
        updateNavigationButtonsAI();
        
        const modalElement = document.getElementById('studyViewModalAI');
        const modal = new bootstrap.Modal(modalElement);
        
        // Remove backdrop when modal is hidden (same as Human modal)
        modalElement.addEventListener('hidden.bs.modal', function() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.paddingRight = '';
        });
        
        modal.show();
    }

    // Make functions globally available
    window.selectStudyStatusAI = function(status) {
        selectedStatusAI = status;
        const includeCard = document.getElementById('includeCardAI');
        const excludeCard = document.getElementById('excludeCardAI');
        
        if (status === 'include') {
            includeCard.style.border = '2px solid #28a745';
            excludeCard.style.border = '2px solid transparent';
        } else if (status === 'exclude') {
            includeCard.style.border = '2px solid transparent';
            excludeCard.style.border = '2px solid #dc3545';
        }
        
        // No auto-save - user must click Save or Save and Next (same as Human modal)
    }

    // Make navigateStudyAI globally available (replicated from Human modal)
    window.navigateStudyAI = function(direction) {
        if (allStudiesForNavigationAI.length === 0) return;
        
        const totalRows = allStudiesForNavigationAI.length;
        let newIndex = currentStudyRowIndexAI;
        
        if (direction === 'next' && currentStudyRowIndexAI < totalRows - 1) {
            newIndex = currentStudyRowIndexAI + 1;
        } else if (direction === 'prev' && currentStudyRowIndexAI > 0) {
            newIndex = currentStudyRowIndexAI - 1;
        }
        
        if (newIndex !== currentStudyRowIndexAI && newIndex >= 0 && newIndex < totalRows) {
            loadStudyDataAI(allStudiesForNavigationAI[newIndex], newIndex);
        }
    };

    function updateNavigationButtonsAI() {
        const prevBtn = document.getElementById('prevStudyBtnAI');
        const saveAndNextBtn = document.getElementById('saveAndNextBtnAI');
        const nextBtn = document.getElementById('nextStudyBtnAI');
        
        if (allStudiesForNavigationAI.length === 0) {
            prevBtn.disabled = true;
            saveAndNextBtn.disabled = true;
            nextBtn.disabled = true;
            return;
        }
        
        const totalRows = allStudiesForNavigationAI.length;
        const isFirst = currentStudyRowIndexAI <= 0;
        const isLast = currentStudyRowIndexAI >= totalRows - 1 || currentStudyRowIndexAI < 0;
        
        prevBtn.disabled = isFirst;
        saveAndNextBtn.disabled = isLast;
        nextBtn.disabled = isLast;
    }

    // Make saveAndNextAI globally available (replicated from Human modal, without criteria)
    window.saveAndNextAI = function() {
        const studyId = document.getElementById('study_view_ai_id').value;
        if (!studyId) return;
        
        if (!selectedStatusAI) {
            if (typeof window.showToast === 'function') {
                window.showToast('Please select Include or Exclude', 'warning');
            } else {
                alert('Please select Include or Exclude');
            }
            return;
        }
        
        const projectId = {{ $project->id }};
        const finalScreeningId = {{ $finalScreening->id }};
        const saveAndNextBtn = document.getElementById('saveAndNextBtnAI');
        const originalText = saveAndNextBtn.innerHTML;
        saveAndNextBtn.disabled = true;
        saveAndNextBtn.innerHTML = '<i class="icon-base ri ri-loader-4-line"></i> Saving...';
        
        fetch('/projects/' + projectId + '/studies/' + studyId + '/screening-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                status: selectedStatusAI,
                criteria_ids: [], // No criteria for AI types
                screening_id: finalScreeningId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const updatedStatus = selectedStatusAI === 'include' ? true : (selectedStatusAI === 'exclude' ? false : null);
                if (allStudiesForNavigationAI[currentStudyRowIndexAI]) {
                    allStudiesForNavigationAI[currentStudyRowIndexAI].status_selecao_final = updatedStatus;
                }
                if (currentStudyDataAI) {
                    currentStudyDataAI.status_selecao_final = updatedStatus;
                }
                navigateStudyAI('next');
                if (window.studiesTableAI) {
                    window.studiesTableAI.ajax.reload(null, false);
                }
                if (typeof window.showToast === 'function') {
                    window.showToast('Study updated successfully!', 'success');
                } else {
                    alert('Study updated successfully!');
                }
            } else {
                if (typeof window.showToast === 'function') {
                    window.showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Error saving study. Please try again.', 'error');
            } else {
                alert('Error saving study. Please try again.');
            }
        })
        .finally(() => {
            saveAndNextBtn.disabled = false;
            saveAndNextBtn.innerHTML = originalText;
            updateNavigationButtonsAI();
        });
    };

    // Save button handler for AI (replicated from Human modal, without criteria)
    const saveStudyViewBtnAI = document.getElementById('saveStudyViewBtnAI');
    if (saveStudyViewBtnAI) {
        saveStudyViewBtnAI.addEventListener('click', function() {
            const studyId = document.getElementById('study_view_ai_id').value;
            if (!studyId) return;
            
            if (!selectedStatusAI) {
                if (typeof window.showToast === 'function') {
                    window.showToast('Please select Include or Exclude', 'warning');
                } else {
                    alert('Please select Include or Exclude');
                }
                return;
            }
            
            const projectId = {{ $project->id }};
            const finalScreeningId = {{ $finalScreening->id }};
            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="icon-base ri ri-loader-4-line"></i> Saving...';
            
            fetch('/projects/' + projectId + '/studies/' + studyId + '/screening-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    status: selectedStatusAI,
                    criteria_ids: [], // No criteria for AI types
                    screening_id: finalScreeningId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const updatedStatus = selectedStatusAI === 'include' ? true : (selectedStatusAI === 'exclude' ? false : null);
                    if (allStudiesForNavigationAI[currentStudyRowIndexAI]) {
                        allStudiesForNavigationAI[currentStudyRowIndexAI].status_selecao_final = updatedStatus;
                    }
                    if (currentStudyDataAI) {
                        currentStudyDataAI.status_selecao_final = updatedStatus;
                    }
                    const modal = bootstrap.Modal.getInstance(document.getElementById('studyViewModalAI'));
                    modal.hide();
                    if (window.studiesTableAI) {
                        window.studiesTableAI.ajax.reload(null, false);
                    }
                    if (typeof window.showToast === 'function') {
                        window.showToast('Study updated successfully!', 'success');
                    } else {
                        alert('Study updated successfully!');
                    }
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof window.showToast === 'function') {
                    window.showToast('Error saving study. Please try again.', 'error');
                } else {
                    alert('Error saving study. Please try again.');
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    }

    // Remove backdrop on modal close for AI modal
    document.getElementById('studyViewModalAI').addEventListener('hidden.bs.modal', function() {
        document.body.classList.remove('modal-open');
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
    });

    // Process button handler for AI OpenRouter
    const processBtn = document.getElementById('processPendingStudiesBtn');
    if (processBtn) {
        processBtn.addEventListener('click', function() {
            const screeningId = {{ $finalScreening->id }};
            const projectId = {{ $project->id }};

            if (!confirm('This will process with AI only the studies that have no result yet. Continue?')) {
                return;
            }

            const originalText = processBtn.innerHTML;
            processBtn.disabled = true;
            processBtn.innerHTML = '<i class="icon-base ri ri-loader-4-line me-1"></i>Processing...';

            fetch(`/projects/${projectId}/final-screenings/${screeningId}/process`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message || 'Processing started successfully!', 'success');
                    } else {
                        alert(data.message || 'Processing started successfully!');
                    }
                    // Reload the page after a short delay to see updated statuses
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message || 'Error starting processing.', 'error');
                    } else {
                        alert(data.message || 'Error starting processing.');
                    }
                    processBtn.disabled = false;
                    processBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error processing studies:', error);
                if (typeof window.showToast === 'function') {
                    window.showToast('Error starting processing. Please try again.', 'error');
                } else {
                    alert('Error starting processing. Please try again.');
                }
                processBtn.disabled = false;
                processBtn.innerHTML = originalText;
            });
        });
    }
});
</script>
@endsection
@endif

<!-- Study View Modal -->
<div class="modal fade" id="studyViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 95vw;">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header">
                <h5 class="modal-title">Study Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height: calc(90vh - 120px); overflow: hidden; display: flex; flex-direction: column;">
                <div class="row g-0 flex-grow-1" style="min-height: 0;">
                    <!-- Left side: PDF Preview -->
                    <div class="col-md-9 border-end d-flex flex-column" style="overflow: hidden; min-height: 0;">
                        <div id="study-pdf-preview" style="flex: 1; overflow: hidden; padding: 0; min-height: 0; display: flex; align-items: stretch; background-color: #f5f6f8;">
                            <div class="d-flex align-items-center justify-content-center h-100 w-100">
                                <div class="text-center text-muted p-5">
                                    <i class="icon-base ri ri-file-pdf-line icon-3x mb-3"></i>
                                    <p class="mb-0">No PDF available for this study</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right side: Study Information and Actions -->
                    <div class="col-md-3" style="height: 100%; overflow: auto;">
                        <div class="p-4">
                            <input type="hidden" id="study_view_id" name="study_id">
                            <span id="study-view-custom-id" class="badge bg-label-primary mb-2"></span>
                            <h5 id="study-view-name" class="mb-3"></h5>
                            
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">DOI</h6>
                                <p id="study-view-doi" class="mb-0"></p>
                            </div>
                            
                            <!-- Include/Exclude Selection -->
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Status</h6>
                                <div class="d-flex gap-2">
                                    <div class="card flex-fill mb-0 cursor-pointer study-status-card" id="includeCard" onclick="selectStudyStatus('include')" style="cursor: pointer; border: 2px solid transparent;">
                                        <div class="card-body p-2 text-center">
                                            <i class="icon-base ri ri-check-line text-success mb-1" style="font-size: 1.5rem;"></i>
                                            <div class="small fw-semibold text-success">Include</div>
                                        </div>
                                    </div>
                                    <div class="card flex-fill mb-0 cursor-pointer study-status-card" id="excludeCard" onclick="selectStudyStatus('exclude')" style="cursor: pointer; border: 2px solid transparent;">
                                        <div class="card-body p-2 text-center">
                                            <i class="icon-base ri ri-close-line text-danger mb-1" style="font-size: 1.5rem;"></i>
                                            <div class="small fw-semibold text-danger">Exclude</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Criteria Selection (hidden by default) -->
                            <div id="criteriaSelectionCard" class="mb-3" style="display: none;">
                                <div class="card">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0" id="criteriaCardTitle">Criteria</h6>
                                    </div>
                                    <div class="card-body p-2" id="criteriaList" style="max-height: 200px; overflow-y: auto;">
                                        <!-- Criteria checkboxes will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-secondary" id="prevStudyBtn" onclick="navigateStudy('prev')">
                        <i class="icon-base ri ri-arrow-left-s-line"></i> Previous
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary me-2" id="saveStudyViewBtn">Save Changes</button>
                    <button type="button" class="btn btn-success me-2" id="saveAndNextBtn" onclick="saveAndNext()">
                        Save and Next <i class="icon-base ri ri-arrow-right-s-line"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="nextStudyBtn" onclick="navigateStudy('next')">
                        Next <i class="icon-base ri ri-arrow-right-s-line"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Study View Modal for AI Types -->
<div class="modal fade" id="studyViewModalAI" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 95vw;">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header">
                <h5 class="modal-title">Study Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height: calc(90vh - 120px); overflow: hidden; display: flex; flex-direction: column;">
                <div class="row g-0 flex-grow-1" style="min-height: 0;">
                    <!-- Left side: PDF Preview -->
                    <div class="col-md-8 border-end d-flex flex-column" style="overflow: hidden; min-height: 0;">
                        <div id="study-pdf-preview-ai" style="flex: 1; overflow: hidden; padding: 0; min-height: 0; display: flex; align-items: stretch; background-color: #f5f6f8;">
                            <div class="d-flex align-items-center justify-content-center h-100 w-100">
                                <div class="text-center text-muted p-5">
                                    <i class="icon-base ri ri-file-pdf-line icon-3x mb-3"></i>
                                    <p class="mb-0">No PDF available for this study</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right side: Study Information and Actions -->
                    <div class="col-md-4" style="height: 100%; overflow: auto;">
                        <div class="p-4">
                            <input type="hidden" id="study_view_ai_id" name="study_id">
                            <span id="study-view-ai-custom-id" class="badge bg-label-primary mb-2"></span>
                            <h5 id="study-view-ai-name" class="mb-3"></h5>
                            
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">DOI</h6>
                                <p id="study-view-ai-doi" class="mb-0"></p>
                            </div>

                            <!-- AI Input -->
                            <div class="mb-4">
                                <h6 class="text-muted mb-2">AI Input (Prompt)</h6>
                                <div class="card bg-light">
                                    <div class="card-body p-3">
                                        <pre id="study-view-ai-input" class="mb-0" style="white-space: pre-wrap; font-size: 0.875rem; max-height: 200px; overflow-y: auto; background: transparent; border: none; padding: 0;"></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- AI Output -->
                            <div class="mb-4">
                                <h6 class="text-muted mb-2">AI Output (Response)</h6>
                                <ul class="nav nav-tabs mb-2" role="tablist">
                                    <li class="nav-item" role="presentation" id="study-view-ai-criteria-item">
                                        <button class="nav-link active" id="study-view-ai-criteria-tab" data-bs-toggle="tab"
                                            data-bs-target="#study-view-ai-criteria-pane" type="button" role="tab">Criteria</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="study-view-ai-raw-tab" data-bs-toggle="tab"
                                            data-bs-target="#study-view-ai-raw-pane" type="button" role="tab">Raw JSON</button>
                                    </li>
                                </ul>
                                <div class="tab-content p-0">
                                    <div class="tab-pane fade show active" id="study-view-ai-criteria-pane" role="tabpanel">
                                        <div id="study-view-ai-criteria"></div>
                                    </div>
                                    <div class="tab-pane fade" id="study-view-ai-raw-pane" role="tabpanel">
                                        <div class="card bg-light">
                                            <div class="card-body p-3">
                                                <pre id="study-view-ai-output" class="mb-0" style="white-space: pre-wrap; font-size: 0.875rem; max-height: 200px; overflow-y: auto; background: transparent; border: none; padding: 0;"></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Include/Exclude Selection -->
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Status</h6>
                                <div class="d-flex gap-2">
                                    <div class="card flex-fill mb-0 cursor-pointer study-status-card-ai" id="includeCardAI" onclick="selectStudyStatusAI('include')" style="cursor: pointer; border: 2px solid transparent;">
                                        <div class="card-body p-2 text-center">
                                            <i class="icon-base ri ri-check-line text-success mb-1" style="font-size: 1.5rem;"></i>
                                            <div class="small fw-semibold text-success">Include</div>
                                        </div>
                                    </div>
                                    <div class="card flex-fill mb-0 cursor-pointer study-status-card-ai" id="excludeCardAI" onclick="selectStudyStatusAI('exclude')" style="cursor: pointer; border: 2px solid transparent;">
                                        <div class="card-body p-2 text-center">
                                            <i class="icon-base ri ri-close-line text-danger mb-1" style="font-size: 1.5rem;"></i>
                                            <div class="small fw-semibold text-danger">Exclude</div>
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
                    <button type="button" class="btn btn-outline-secondary" id="prevStudyBtnAI" onclick="navigateStudyAI('prev')">
                        <i class="icon-base ri ri-arrow-left-s-line"></i> Previous
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary me-2" id="saveStudyViewBtnAI">Save Changes</button>
                    <button type="button" class="btn btn-success me-2" id="saveAndNextBtnAI" onclick="saveAndNextAI()">
                        Save and Next <i class="icon-base ri ri-arrow-right-s-line"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="nextStudyBtnAI" onclick="navigateStudyAI('next')">
                        Next <i class="icon-base ri ri-arrow-right-s-line"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

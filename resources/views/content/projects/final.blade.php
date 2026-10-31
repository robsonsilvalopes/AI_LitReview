@extends('layouts/contentNavbarLayout')

@section('title', $project->name . ' - Final Selection')

@section('vendor-style')
<!-- DataTables CSS (local) -->
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/buttons.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/responsive.bootstrap5.min.css') }}">
@endsection

@section('page-style')
<style>
  #studiesTable_wrapper .dataTables_paginate .pagination {
    justify-content: flex-end;
  }
  .table-danger-light,
  .table-danger-light > td {
    background-color: #fce4e4 !important;
  }
</style>
@endsection

@section('vendor-script')
<!-- jQuery (local, browser build) -->
<script src="{{ asset('assets/vendor/libs/jquery/jquery.min.js') }}"></script>
@endsection

@section('page-script')
<script>
// Initialize DataTables after page loads
document.addEventListener('DOMContentLoaded', function() {
    const jquerySrc = "{{ asset('assets/vendor/libs/jquery/jquery.min.js') }}";
    const dataTablesScripts = [
        "{{ asset('assets/vendor/libs/datatables/js/jquery.dataTables.min.js') }}",
        "{{ asset('assets/vendor/libs/datatables/js/dataTables.bootstrap5.min.js') }}",
        "{{ asset('assets/vendor/libs/datatables/js/dataTables.buttons.min.js') }}",
        "{{ asset('assets/vendor/libs/datatables/js/buttons.bootstrap5.min.js') }}",
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

        console.log('jQuery and DataTables are available, initializing...');

        const projectId = {{ $project->id }};
        const tableElement = document.getElementById('studiesTable');

        if (!tableElement) {
            console.error('Table element not found!');
            return;
        }

        // Initialize DataTables
        const $table = jQuery('#studiesTable');
        const table = (jQuery.fn.DataTable ? $table.DataTable : $table.dataTable).call($table, {
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: '/projects/' + projectId + '/studies',
                type: 'GET',
                data: function(d) {
                    // Filter to only show studies included in initial selection phase
                    d.filter_initial_included = true;
                    // Filter type for final selection criteria
                    d.criteria_type = 'final';
                    console.log('Sending DataTables request:', d);
                    return d;
                },
                headers: {
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                dataSrc: function(json) {
                    console.log('Received DataTables response:', json);
                    console.log('Data count:', json.data ? json.data.length : 0);
                    if (!json.data || !Array.isArray(json.data)) {
                        console.error('Invalid data format:', json);
                        return [];
                    }
                    return json.data;
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTables AJAX error:', error, thrown);
                    console.error('Status:', xhr.status);
                    console.error('Response:', xhr.responseText);
                    alert('Error loading studies. Check console for details.');
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
                        const hasPdf = !!row.pdf_path;
                        const iconClass = hasPdf ? 'ri-file-pdf-line text-danger' : 'ri-file-pdf-line text-muted';
                        const avatarBg = hasPdf ? 'bg-danger-subtle' : 'bg-light';
                        return '<div class="d-flex align-items-center">' +
                            '<div class="avatar me-2 flex-shrink-0" style="cursor: pointer;" onclick="openStudyViewModal(' + row.id + ')">' +
                                '<span class="avatar-initial rounded-circle ' + avatarBg + '">' +
                                    '<i class="icon-base ri ' + iconClass + '"></i>' +
                                '</span>' +
                            '</div>' +
                            '<a href="javascript:void(0);" onclick="openStudyViewModal(' + row.id + ')" class="text-primary fw-semibold" style="cursor: pointer; text-decoration: none;">' + title + '</a>' +
                        '</div>';
                    }
                },
                {
                    targets: 2,
                    render: function(data, type, row) {
                        if (!data) {
                            return 'N/A';
                        }
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
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6">>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pagingType: 'full_numbers'
        });
        
        console.log('DataTable initialized successfully');
        
        // Make table available globally
        window.studiesTable = table;
        window.projectId = projectId;
        let studiesData = [];
        let studyToDelete = null;
        let allStudiesCache = [];

        // Move length control into custom controls row
        const lengthControl = document.querySelector('#studiesTable_wrapper .dataTables_length');
        const lengthTarget = document.getElementById('studiesLength');
        if (lengthControl && lengthTarget) {
            const lengthSelect = lengthControl.querySelector('select');
            const lengthLabel = lengthControl.querySelector('label');
            if (lengthSelect && lengthLabel) {
                const wrapper = document.createElement('div');
                wrapper.className = 'input-group';
                lengthSelect.classList.add('form-select');
                lengthLabel.textContent = '';
                wrapper.appendChild(lengthSelect);
                lengthLabel.appendChild(wrapper);
            }
            lengthTarget.appendChild(lengthControl);
        }
        
        window.loadStudies = function() {
            if (window.studiesTable) {
                window.studiesTable.ajax.reload(null, false);
            }
        };

        const searchInput = document.getElementById('studiesSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                if (window.studiesTable) {
                    window.studiesTable.search(this.value).draw();
                }
            });
        }
        
        // Store data from DataTables response
        table.on('xhr.dt', function(e, settings, json) {
            studiesData = json.data || [];
        });
        
        // Edit study function
        window.editStudy = function(studyId) {
            let study = studiesData.find(s => s.id === studyId);
            if (!study && window.studiesTable) {
                window.studiesTable.rows().every(function() {
                    const rowData = this.data();
                    if (rowData.id === studyId) {
                        study = rowData;
                        return false;
                    }
                });
            }
            if (!study) {
                fetch('/projects/' + projectId + '/studies?per_page=10000', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    const allStudies = data.data || data;
                    const foundStudy = allStudies.find(s => s.id === studyId);
                    if (foundStudy) {
                        populateEditForm(foundStudy);
                    } else {
                        if (typeof window.showToast === 'function') {
                            window.showToast('Study not found', 'error');
                        }
                    }
                });
                return;
            }
            populateEditForm(study);
        };
        
        function populateEditForm(study) {
            document.getElementById('studyModalTitle').textContent = 'Edit Study';
            document.getElementById('study_id').value = study.id;
            document.getElementById('study_custom_id').value = study.custom_id || '';
            document.getElementById('study_name').value = study.name || '';
            document.getElementById('study_year').value = study.year || '';
            document.getElementById('study_authors').value = study.authors || '';
            document.getElementById('study_document_type').value = study.document_type || '';
            document.getElementById('study_journal_conference').value = study.journal_conference || '';
            document.getElementById('study_tags').value = study.tags || '';
            document.getElementById('study_notes').value = study.notes || '';
            document.getElementById('study_doi').value = study.doi || '';
            document.getElementById('study_link').value = study.link || '';
            document.getElementById('study_keywords').value = study.keywords || '';
            document.getElementById('study_source').value = study.source || '';
            document.getElementById('study_abstract').value = study.abstract || '';
            document.getElementById('study_status_selecao_final').value = study.status_selecao_final === null ? '' : (study.status_selecao_final ? '1' : '0');
            document.getElementById('study_status_selecao_final').value = study.status_selecao_final === null ? '' : (study.status_selecao_final ? '1' : '0');
            document.getElementById('study_status_selecao_final').value = study.status_selecao_final === null ? '' : (study.status_selecao_final ? '1' : '0');
            document.getElementById('study_status_extracao').value = study.status_extracao === null ? '' : (study.status_extracao ? '1' : '0');
            document.getElementById('study_pdf').value = '';
            const modal = new bootstrap.Modal(document.getElementById('createStudyModal'));
            modal.show();
        }
        
        // Store all studies for navigation (in DataTable order)
        let allStudiesForNavigation = [];
        
        // Open study view modal
        window.openStudyViewModal = function(studyId) {
            studyId = studyId != null ? Number(studyId) : null;
            if (studyId == null || isNaN(studyId)) {
                if (typeof window.showToast === 'function') {
                    window.showToast('Invalid study', 'error');
                }
                return;
            }
            // Get current DataTable sort order
            let sortColumn = 'id';
            let sortDir = 'asc';
            
            if (window.studiesTable) {
                const order = window.studiesTable.order();
                if (order && order.length > 0) {
                    const columnIdx = order[0][0];
                    sortDir = order[0][1];
                    // Map column index to column name
                    const columnNames = ['custom_id', 'name', 'doi', 'abstract', 'status_selecao_final', 'inclusion_criteria', 'exclusion_criteria'];
                    sortColumn = columnNames[columnIdx] || 'id';
                }
            }
            
            // Fetch ALL studies from server with same sort order and criteria as DataTable
            fetch('/projects/' + projectId + '/studies?length=10000&per_page=10000&sort_by=' + sortColumn + '&sort_dir=' + sortDir + '&filter_initial_included=true&criteria_type=final', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                const raw = data.data != null ? data.data : data;
                allStudiesForNavigation = Array.isArray(raw) ? raw : [];
                // Compare with loose equality so string "123" matches number 123
                const rowIndex = allStudiesForNavigation.findIndex(s => s != null && (s.id == studyId || Number(s.id) === studyId));
                const foundStudy = rowIndex >= 0 ? allStudiesForNavigation[rowIndex] : null;
                
                if (foundStudy) {
                    displayStudyInModal(foundStudy, rowIndex);
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Study not found', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error loading study:', error);
                if (typeof window.showToast === 'function') {
                    window.showToast('Error loading study', 'error');
                }
            });
        };
        
        // Store current study data and criteria for the modal
        let currentStudyData = null;
        let currentStudyRowIndex = -1;
        let projectInclusionCriteria = [];
        let projectExclusionCriteria = [];
        let selectedStatus = null;
        
        function displayStudyInModal(study, rowIndex = -1) {
            currentStudyData = study;
            currentStudyRowIndex = rowIndex;
            document.getElementById('study_view_id').value = study.id;
            
            // Update navigation buttons state
            updateNavigationButtons();
            document.getElementById('study-view-custom-id').textContent = study.custom_id || 'N/A';
            document.getElementById('study-view-name').textContent = study.name || 'N/A';
            let abstractText = study.abstract || 'No abstract available';
            document.getElementById('study-view-abstract').textContent = abstractText;
            
            // Load screenings for this study
            loadStudyScreenings(study.id);
            
            // Reset status selection
            resetStatusSelection();
            
            // Load project criteria first, then set status
            loadProjectCriteria().then(() => {
                // Set current status based on study data after criteria are loaded
                if (study.status_selecao_final === true) {
                    selectStudyStatus('include', true);
                } else if (study.status_selecao_final === false) {
                    selectStudyStatus('exclude', true);
                }
            });
            
            const modal = new bootstrap.Modal(document.getElementById('studyViewModal'));
            modal.show();
        }
        
        let studyScreeningsData = [];

        window.openScreeningOutput = function(index) {
            window.showScreeningOutput(studyScreeningsData[index]);
        };

        function loadStudyScreenings(studyId) {
            const tbody = document.getElementById('studyScreeningsTableBody');
            studyScreeningsData = [];
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted"><i class="icon-base ri ri-loader-4-line"></i> Loading...</td></tr>';
            
            fetch('/projects/' + projectId + '/studies/' + studyId + '/screenings?type=final', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No screenings found</td></tr>';
                    } else {
                        studyScreeningsData = data.data;
                        tbody.innerHTML = data.data.map((screening, index) => {
                            let statusBadge = '';
                            if (screening.status === 'Included') {
                                statusBadge = '<span class="badge rounded-pill bg-success">Included</span>';
                            } else if (screening.status === 'Excluded') {
                                statusBadge = '<span class="badge rounded-pill bg-danger">Excluded</span>';
                            } else {
                                statusBadge = '<span class="badge rounded-pill bg-secondary">N/A</span>';
                            }
                            
                            return '<tr style="cursor: pointer;" title="View model output" onclick="openScreeningOutput(' + index + ')">' +
                                '<td>' + (screening.name || '-') + '</td>' +
                                '<td>' + (screening.type || '-') + '</td>' +
                                '<td>' + (screening.ai_model || '-') + '</td>' +
                                '<td>' + (screening.template || '-') + '</td>' +
                                '<td>' + (screening.responsible || '-') + '</td>' +
                                '<td>' + (screening.date || '-') + '</td>' +
                                '<td>' + statusBadge + '</td>' +
                                '</tr>';
                        }).join('');
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading screenings</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error loading screenings:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading screenings</td></tr>';
            });
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
        
        window.saveAndNext = function() {
            const studyId = document.getElementById('study_view_id').value;
            if (!studyId) return;
            
            // Get selected criteria IDs
            const selectedCriteriaIds = [];
            document.querySelectorAll('.criteria-checkbox:checked').forEach(function(checkbox) {
                selectedCriteriaIds.push(parseInt(checkbox.value));
            });
            
            const saveAndNextBtn = document.getElementById('saveAndNextBtn');
            const originalText = saveAndNextBtn.innerHTML;
            saveAndNextBtn.disabled = true;
            saveAndNextBtn.innerHTML = '<i class="icon-base ri ri-loader-4-line"></i> Saving...';
            
            // Save study status and criteria
            fetch('/projects/' + projectId + '/studies/' + studyId + '/final-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    status: selectedStatus,
                    criteria_ids: selectedCriteriaIds
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || 'Network response was not ok');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update the study in our navigation array with the new data
                    const updatedStatus = selectedStatus === 'include' ? true : (selectedStatus === 'exclude' ? false : null);
                    if (allStudiesForNavigation[currentStudyRowIndex]) {
                        allStudiesForNavigation[currentStudyRowIndex].status_selecao_final = updatedStatus;
                        allStudiesForNavigation[currentStudyRowIndex].inclusion_criteria_final = selectedStatus === 'include' ? selectedCriteriaIds : [];
                        allStudiesForNavigation[currentStudyRowIndex].exclusion_criteria_final = selectedStatus === 'exclude' ? selectedCriteriaIds : [];
                    }
                    // Also update currentStudyData
                    if (currentStudyData) {
                        currentStudyData.status_selecao_final = updatedStatus;
                        currentStudyData.inclusion_criteria_final = selectedStatus === 'include' ? selectedCriteriaIds : [];
                        currentStudyData.exclusion_criteria_final = selectedStatus === 'exclude' ? selectedCriteriaIds : [];
                    }
                    
                    // Navigate to next study
                    navigateStudy('next');
                    // Reload table data in background
                    if (window.studiesTable) {
                        window.studiesTable.ajax.reload(null, false);
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
        
        window.navigateStudy = function(direction) {
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
            
            // Get the study at the new index directly from our stored array
            const newStudy = allStudiesForNavigation[newIndex];
            
            if (newStudy) {
                // Update current study data and index
                currentStudyData = newStudy;
                currentStudyRowIndex = newIndex;
                
                // Update study ID
                document.getElementById('study_view_id').value = newStudy.id;
                
                // Update custom ID badge
                document.getElementById('study-view-custom-id').textContent = newStudy.custom_id || 'N/A';
                
                // Update study name
                document.getElementById('study-view-name').textContent = newStudy.name || 'N/A';
                
                // Update abstract
                let abstractText = newStudy.abstract || 'No abstract available';
                document.getElementById('study-view-abstract').textContent = abstractText;
                
                // Load screenings for this study
                loadStudyScreenings(newStudy.id);
                
                // Reset status selection
                resetStatusSelection();
                
                // Load project criteria first, then set status
                loadProjectCriteria().then(() => {
                    // Set current status based on study data after criteria are loaded
                    if (newStudy.status_selecao_final === true) {
                        selectStudyStatus('include', true);
                    } else if (newStudy.status_selecao_final === false) {
                        selectStudyStatus('exclude', true);
                    }
                });
                
                // Update navigation buttons
                updateNavigationButtons();
            }
        };
        
        function resetStatusSelection() {
            selectedStatus = null;
            document.getElementById('includeCard').style.border = '2px solid transparent';
            document.getElementById('includeCard').style.backgroundColor = '';
            document.getElementById('excludeCard').style.border = '2px solid transparent';
            document.getElementById('excludeCard').style.backgroundColor = '';
            document.getElementById('criteriaSelectionCard').style.display = 'none';
            document.getElementById('criteriaList').innerHTML = '';
        }
        
        window.selectStudyStatus = function(status, loadCriteria = true) {
            selectedStatus = status;
            
            // Update card styles
            const includeCard = document.getElementById('includeCard');
            const excludeCard = document.getElementById('excludeCard');
            
            if (status === 'include') {
                includeCard.style.border = '2px solid var(--bs-success)';
                includeCard.style.backgroundColor = 'rgba(40, 199, 111, 0.1)';
                excludeCard.style.border = '2px solid transparent';
                excludeCard.style.backgroundColor = '';
                document.getElementById('criteriaCardTitle').textContent = 'Inclusion Criteria';
            } else {
                excludeCard.style.border = '2px solid var(--bs-danger)';
                excludeCard.style.backgroundColor = 'rgba(234, 84, 85, 0.1)';
                includeCard.style.border = '2px solid transparent';
                includeCard.style.backgroundColor = '';
                document.getElementById('criteriaCardTitle').textContent = 'Exclusion Criteria';
            }
            
            // Show criteria card and populate
            document.getElementById('criteriaSelectionCard').style.display = 'block';
            if (loadCriteria) {
                populateCriteriaList(status);
            } else {
                populateCriteriaList(status);
            }
        };
        
        function loadProjectCriteria() {
            // Load inclusion and exclusion criteria in parallel
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
            
            // Return a promise that resolves when both are done
            return Promise.all([inclusionPromise, exclusionPromise]);
        }
        
        function populateCriteriaList(status) {
            const criteriaList = document.getElementById('criteriaList');
            const criteria = status === 'include' ? projectInclusionCriteria : projectExclusionCriteria;
            const selectedCriteriaIds = status === 'include' 
                ? (currentStudyData.inclusion_criteria_final || [])
                : (currentStudyData.exclusion_criteria_final || []);
            
            if (!criteria || criteria.length === 0) {
                criteriaList.innerHTML = '<p class="text-muted small mb-0">No criteria available</p>';
                return;
            }
            
            let html = '';
            criteria.forEach(function(item) {
                const criteriaId = item.custom_id || item.id;
                const criteriaDesc = status === 'include' ? item.inclusion_criteria : item.exclusion_criteria;
                // Compare with item.id (the actual ID we store when saving)
                const isChecked = selectedCriteriaIds.includes(item.id) ? 'checked' : '';
                
                html += '<div class="form-check mb-2">' +
                    '<input class="form-check-input criteria-checkbox" type="checkbox" value="' + item.id + '" id="criteria_' + item.id + '" ' + isChecked + '>' +
                    '<label class="form-check-label small" for="criteria_' + item.id + '">' +
                        '<strong>' + criteriaId + '</strong> - ' + (criteriaDesc || 'N/A') +
                    '</label>' +
                '</div>';
            });
            
            criteriaList.innerHTML = html;
        }
        
        // Delete study function
        window.deleteStudy = function(studyId) {
            let study = studiesData.find(s => s.id === studyId);
            if (!study && window.studiesTable) {
                window.studiesTable.rows().every(function() {
                    const rowData = this.data();
                    if (rowData.id === studyId) {
                        study = rowData;
                        return false;
                    }
                });
            }
            if (!study) {
                if (typeof window.showToast === 'function') {
                    window.showToast('Study not found', 'error');
                }
                return;
            }
            studyToDelete = study;
            document.getElementById('delete-study-name').textContent = study.name || 'N/A';
            const modal = new bootstrap.Modal(document.getElementById('deleteStudyModal'));
            modal.show();
        };
        
        // Handle study form submission
        const studyForm = document.getElementById('studyForm');
        if (studyForm) {
            studyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData();
                const studyId = document.getElementById('study_id').value;
                const isEdit = studyId !== '';
                formData.append('custom_id', document.getElementById('study_custom_id').value);
                formData.append('name', document.getElementById('study_name').value);
                formData.append('year', document.getElementById('study_year').value);
                formData.append('authors', document.getElementById('study_authors').value);
                formData.append('document_type', document.getElementById('study_document_type').value);
                formData.append('journal_conference', document.getElementById('study_journal_conference').value);
                formData.append('tags', document.getElementById('study_tags').value);
                formData.append('notes', document.getElementById('study_notes').value);
                formData.append('doi', document.getElementById('study_doi').value);
                formData.append('link', document.getElementById('study_link').value);
                formData.append('keywords', document.getElementById('study_keywords').value);
                formData.append('source', document.getElementById('study_source').value);
                formData.append('abstract', document.getElementById('study_abstract').value);
                const statusDuplicadosExclusao = document.getElementById('study_status_selecao_final').value;
                formData.append('status_selecao_final', statusDuplicadosExclusao === '' ? '' : (statusDuplicadosExclusao === '1' ? '1' : '0'));
                const statusInitial = document.getElementById('study_status_selecao_final').value;
                formData.append('status_selecao_final', statusInitial === '' ? '' : (statusInitial === '1' ? '1' : '0'));
                const statusFinal = document.getElementById('study_status_selecao_final').value;
                formData.append('status_selecao_final', statusFinal === '' ? '' : (statusFinal === '1' ? '1' : '0'));
                const statusExtracao = document.getElementById('study_status_extracao').value;
                formData.append('status_extracao', statusExtracao === '' ? '' : (statusExtracao === '1' ? '1' : '0'));
                const pdfFile = document.getElementById('study_pdf').files[0];
                if (pdfFile) {
                    formData.append('pdf', pdfFile);
                }
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                const submitButton = studyForm.querySelector('button[type="submit"]');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Saving...';
                const url = isEdit ? `/projects/${projectId}/studies/${studyId}` : `/projects/${projectId}/studies`;
                const method = isEdit ? 'PUT' : 'POST';
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': formData.get('_token'),
                        'X-HTTP-Method-Override': method,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createStudyModal'));
                        modal.hide();
                        studyForm.reset();
                        document.getElementById('study_id').value = '';
                        document.getElementById('studyModalTitle').textContent = 'Create Study';
                        loadStudies();
                        if (typeof window.showToast === 'function') {
                            window.showToast(data.message, 'success');
                        } else {
                            alert(data.message);
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
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                });
            });
        }
        
        // Handle delete study confirmation
        const confirmDeleteStudyBtn = document.getElementById('confirmDeleteStudy');
        if (confirmDeleteStudyBtn) {
            confirmDeleteStudyBtn.addEventListener('click', function() {
                if (!studyToDelete) return;
                fetch(`/projects/${projectId}/studies/${studyToDelete.id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-HTTP-Method-Override': 'DELETE',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteStudyModal'));
                        modal.hide();
                        studyToDelete = null;
                        loadStudies();
                        if (typeof window.showToast === 'function') {
                            window.showToast(data.message, 'success');
                        } else {
                            alert(data.message);
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
                        window.showToast('Error deleting study. Please try again.', 'error');
                    } else {
                        alert('Error deleting study. Please try again.');
                    }
                });
            });
        }
        
        // Reset modal when closed
        const createStudyModal = document.getElementById('createStudyModal');
        if (createStudyModal) {
            createStudyModal.addEventListener('hidden.bs.modal', function() {
                document.getElementById('studyForm').reset();
                document.getElementById('study_id').value = '';
                document.getElementById('studyModalTitle').textContent = 'Create Study';
            });
        }
        
        // Save study view changes
        const saveStudyViewBtn = document.getElementById('saveStudyViewBtn');
        if (saveStudyViewBtn) {
            saveStudyViewBtn.addEventListener('click', function() {
                const studyId = document.getElementById('study_view_id').value;
                if (!studyId) return;
                
                // Get selected criteria IDs
                const selectedCriteriaIds = [];
                document.querySelectorAll('.criteria-checkbox:checked').forEach(function(checkbox) {
                    selectedCriteriaIds.push(parseInt(checkbox.value));
                });
                
                // Determine status value
                let statusValue = null;
                if (selectedStatus === 'include') {
                    statusValue = true;
                } else if (selectedStatus === 'exclude') {
                    statusValue = false;
                }
                
                const submitButton = saveStudyViewBtn;
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Saving...';
                
                // Save study status and criteria
                fetch('/projects/' + projectId + '/studies/' + studyId + '/final-status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        status: selectedStatus,
                        criteria_ids: selectedCriteriaIds
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(data.message || 'Network response was not ok');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Update the study in our navigation array with the new data
                        if (allStudiesForNavigation[currentStudyRowIndex]) {
                            allStudiesForNavigation[currentStudyRowIndex].status_selecao_final = statusValue;
                            allStudiesForNavigation[currentStudyRowIndex].inclusion_criteria_final = selectedStatus === 'include' ? selectedCriteriaIds : [];
                            allStudiesForNavigation[currentStudyRowIndex].exclusion_criteria_final = selectedStatus === 'exclude' ? selectedCriteriaIds : [];
                        }
                        // Also update currentStudyData
                        if (currentStudyData) {
                            currentStudyData.status_selecao_final = statusValue;
                            currentStudyData.inclusion_criteria_final = selectedStatus === 'include' ? selectedCriteriaIds : [];
                            currentStudyData.exclusion_criteria_final = selectedStatus === 'exclude' ? selectedCriteriaIds : [];
                        }
                        
                        const modal = bootstrap.Modal.getInstance(document.getElementById('studyViewModal'));
                        modal.hide();
                        if (typeof window.showToast === 'function') {
                            window.showToast('Study updated successfully!', 'success');
                        } else {
                            alert('Study updated successfully!');
                        }
                        // Reload table data in background
                        if (window.studiesTable) {
                            window.studiesTable.ajax.reload(null, false);
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
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                });
            });
        }
        
        // Import Excel
        const importExcelForm = document.getElementById('importExcelForm');
        if (importExcelForm) {
            importExcelForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const excelFile = document.getElementById('excel_file').files[0];
                if (!excelFile) {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Please select an Excel file', 'warning');
                    } else {
                        alert('Please select an Excel file');
                    }
                    return;
                }
                const formData = new FormData();
                formData.append('excel_file', excelFile);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                const submitButton = document.getElementById('importExcelSubmitBtn');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Importing...';
                fetch(`/projects/${projectId}/studies/import-excel`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': formData.get('_token'),
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('importExcelModal'));
                        modal.hide();
                        importExcelForm.reset();
                        loadStudies();
                        let message = data.message;
                        if (data.errors && data.errors.length > 0) {
                            message += '\nErrors: ' + data.errors.slice(0, 5).join(', ');
                            if (data.errors.length > 5) {
                                message += ' and ' + (data.errors.length - 5) + ' more...';
                            }
                        }
                        if (typeof window.showToast === 'function') {
                            window.showToast(message, 'success');
                        } else {
                            alert(message);
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
                        window.showToast('Error importing Excel file. Please try again.', 'error');
                    } else {
                        alert('Error importing Excel file. Please try again.');
                    }
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                });
            });
        }
        
        // Bulk upload PDFs
        const bulkUploadForm = document.getElementById('bulkUploadForm');
        if (bulkUploadForm) {
            bulkUploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const pdfFiles = document.getElementById('bulk_pdfs').files;
                if (pdfFiles.length === 0) {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Please select at least one PDF file', 'warning');
                    } else {
                        alert('Please select at least one PDF file');
                    }
                    return;
                }
                const formData = new FormData();
                for (let i = 0; i < pdfFiles.length; i++) {
                    formData.append('pdfs[]', pdfFiles[i]);
                }
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                const submitButton = document.getElementById('bulkUploadSubmitBtn');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Uploading...';
                fetch(`/projects/${projectId}/studies/bulk-upload`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': formData.get('_token'),
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('uploadResults').style.display = 'block';
                        const assignedList = document.getElementById('assignedFilesList');
                        assignedList.innerHTML = '';
                        if (data.results.assigned.length > 0) {
                            data.results.assigned.forEach(item => {
                                const li = document.createElement('li');
                                li.className = 'list-group-item';
                                li.innerHTML = `<strong>${item.filename}</strong> → Assigned to <strong>${item.study_custom_id}</strong> (${item.study_name || 'N/A'})`;
                                assignedList.appendChild(li);
                            });
                        } else {
                            assignedList.innerHTML = '<li class="list-group-item text-muted">No files were automatically assigned</li>';
                        }
                        const unassignedList = document.getElementById('unassignedFilesList');
                        unassignedList.innerHTML = '';
                        if (data.results.unassigned.length > 0) {
                            data.results.unassigned.forEach((item, index) => {
                                const div = document.createElement('div');
                                div.className = 'mb-3 p-3 border rounded';
                                div.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <strong>${item.temp_filename}</strong>
                                            <select class="form-select mt-2" id="study_select_${index}" data-temp-path="${item.temp_path}" data-temp-filename="${item.temp_filename}">
                                                <option value="">-- Select Study --</option>
                                                ${studiesData.map(study => `<option value="${study.id}">${study.custom_id || 'N/A'} - ${study.name || 'N/A'}</option>`).join('')}
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary ms-3" onclick="assignPdfToStudy(${index})">Assign</button>
                                    </div>
                                `;
                                unassignedList.appendChild(div);
                            });
                        } else {
                            unassignedList.innerHTML = '<p class="text-muted">All files were automatically assigned!</p>';
                        }
                        loadStudies();
                        if (typeof window.showToast === 'function') {
                            window.showToast(`Upload completed: ${data.results.assigned.length} assigned, ${data.results.unassigned.length} need manual assignment`, 'success');
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
                        window.showToast('Error uploading PDFs. Please try again.', 'error');
                    } else {
                        alert('Error uploading PDFs. Please try again.');
                    }
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                });
            });
        }
        
        // Assign PDF to study manually
        window.assignPdfToStudy = function(index) {
            const select = document.getElementById(`study_select_${index}`);
            const studyId = select.value;
            const tempPath = select.getAttribute('data-temp-path');
            const tempFilename = select.getAttribute('data-temp-filename');
            if (!studyId) {
                if (typeof window.showToast === 'function') {
                    window.showToast('Please select a study', 'warning');
                } else {
                    alert('Please select a study');
                }
                return;
            }
            fetch(`/projects/${projectId}/studies/assign-pdf`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    study_id: studyId,
                    temp_path: tempPath,
                    temp_filename: tempFilename
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const selectElement = document.getElementById(`study_select_${index}`);
                    const container = selectElement.closest('.border');
                    container.remove();
                    loadStudies();
                    if (typeof window.showToast === 'function') {
                        window.showToast('PDF assigned successfully!', 'success');
                    } else {
                        alert('PDF assigned successfully!');
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
                    window.showToast('Error assigning PDF. Please try again.', 'error');
                } else {
                    alert('Error assigning PDF. Please try again.');
                }
            });
        };
        
        // Reset bulk upload modal when closed
        const bulkUploadModal = document.getElementById('bulkUploadModal');
        if (bulkUploadModal) {
            bulkUploadModal.addEventListener('hidden.bs.modal', function() {
                document.getElementById('bulkUploadForm').reset();
                document.getElementById('uploadResults').style.display = 'none';
            });
        }
        
    }

    ensureJQuery()
        .then(ensureDataTables)
        .then(initDataTable)
        .then(initInitialScreeningsTable)
        .catch((error) => {
            console.error('Failed to load jQuery/DataTables:', error);
        });
});

// Final Screenings DataTable
let finalScreeningsTable = null;
let deleteScreeningId = null;
let editScreeningId = null;

function initInitialScreeningsTable() {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
        console.error('jQuery or DataTables not available for screenings table');
        return;
    }

    const projectId = {{ $project->id }};
    const $table = jQuery('#finalScreeningsTable');
    
    finalScreeningsTable = $table.DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
            url: '/projects/' + projectId + '/final-screenings',
            type: 'GET',
            headers: {
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            dataSrc: function(json) {
                if (json.success !== undefined && !json.success) {
                    console.error('Error loading screenings:', json.message);
                    return [];
                }
                return json.data || [];
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
            }
        },
        columns: [
            { data: 'nome', name: 'nome', orderable: true, searchable: true },
            { data: 'tipo', name: 'tipo', orderable: true, searchable: true },
            { data: 'ai_model', name: 'ai_model', orderable: false, searchable: false },
            { data: 'template', name: 'template', orderable: false, searchable: false },
            { data: 'responsavel', name: 'responsavel', orderable: true, searchable: false },
            { data: 'data', name: 'data', orderable: true, searchable: false },
            { data: 'progress', name: 'progress', orderable: true, searchable: false },
            { data: 'id', name: 'actions', orderable: false, searchable: false }
        ],
        columnDefs: [
            {
                targets: 3,
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            {
                targets: 6,
                render: function(data, type, row) {
                    const progress = parseFloat(data) || 0;
                    const classified = row.classified_studies || 0;
                    const total = row.total_studies || 0;
                    
                    return '<div class="mt-3">' +
                           '<div class="d-flex justify-content-between mb-1">' +
                           '<span class="text-muted">Progress</span>' +
                           '<span class="text-muted">' + progress.toFixed(1) + '%</span>' +
                           '</div>' +
                           '<div class="progress" style="height: 8px;">' +
                           '<div class="progress-bar" role="progressbar" style="width: ' + progress + '%;" aria-valuenow="' + progress + '" aria-valuemin="0" aria-valuemax="100"></div>' +
                           '</div>' +
                           '</div>';
                }
            },
            {
                targets: 7,
                render: function(data, type, row) {
                    return '<button type="button" class="btn btn-sm btn-label-primary me-1" onclick="event.stopPropagation(); openEditInitialScreeningModal(' + data + ')">' +
                           '<i class="icon-base ri ri-pencil-line me-1"></i>Edit</button>' +
                           '<button type="button" class="btn btn-sm btn-label-danger" onclick="event.stopPropagation(); openDeleteFinalScreeningModal(' + data + ', \'' + row.nome.replace(/'/g, "\\'") + '\')">' +
                           '<i class="icon-base ri ri-delete-bin-line me-1"></i>Delete</button>';
                }
            }
        ],
        order: [[4, 'desc']],
        pageLength: 10,
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
    
    // Make rows clickable to open screening view
    $table.on('click', 'tbody tr', function(e) {
        // Don't trigger if clicking on buttons
        if (jQuery(e.target).closest('button').length > 0) {
            return;
        }
        const data = finalScreeningsTable.row(this).data();
        if (data && data.id) {
            const projectId = {{ $project->id }};
            window.location.href = '/projects/' + projectId + '/final-screenings/' + data.id;
        }
    });
    
    // Add cursor pointer style to table rows
    $table.find('tbody').css('cursor', 'pointer');

    // Load users for responsavel dropdown (pre-load for modal)
    // This will be called again when modal opens, but pre-loading helps
    setTimeout(loadUsersForScreening, 500);
    
    // Auto-refresh the table every 3 seconds to update progress (silently, without loading indicator)
    setInterval(function() {
        if (finalScreeningsTable) {
            // Hide processing indicator before reload
            const processingElement = $table.closest('.dataTables_wrapper').find('.dataTables_processing');
            if (processingElement.length) {
                processingElement.css('display', 'none');
            }
            
            // Reload without resetting pagination
            finalScreeningsTable.ajax.reload(function(json) {
                // Keep processing hidden after reload completes
                if (processingElement.length) {
                    processingElement.css('display', 'none');
                }
            }, false);
        }
    }, 3000);
}

function loadUsersForScreening() {
    const projectId = {{ $project->id }};
    const currentUserId = {{ Auth::id() }};
    const select = document.getElementById('screening_responsavel_id');
    
    if (!select) {
        console.error('Select element not found');
        return;
    }
    
    fetch('/projects/' + projectId + '/final-screenings/users', {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            select.innerHTML = '<option value="">Select User</option>';
            data.data.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name + ' (' + user.email + ')';
                if (user.id === currentUserId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        } else {
            console.error('Failed to load users:', data);
        }
    })
    .catch(error => {
        console.error('Error loading users:', error);
    });
}

function getNextScreeningName() {
    const projectId = {{ $project->id }};
    return fetch('/projects/' + projectId + '/final-screenings?draw=1&start=0&length=1', {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        let count = 1;
        if (data.recordsTotal !== undefined) {
            count = data.recordsTotal + 1;
        } else if (data.data && Array.isArray(data.data)) {
            count = data.data.length + 1;
        }
        return 'Screening ' + count;
    })
    .catch(error => {
        console.error('Error getting screening count:', error);
        return 'Screening 1';
    });
}

function loadAvailableModelsForScreening(tipo) {
    const projectId = {{ $project->id }};
    const select = document.getElementById('screening_ai_model_id');
    const container = document.getElementById('screening_ai_model_container');
    const templateContainer = document.getElementById('screening_template_container');
    
    const templateSelect = document.getElementById('screening_template_id');
    if (tipo === 'Human') {
        container.style.display = 'none';
        templateContainer.style.display = 'none';
        select.value = '';
        select.required = false;
        if (templateSelect) {
            templateSelect.required = false;
            templateSelect.value = '';
        }
    } else if (tipo === 'AI OpenRouter' || tipo === 'AI Local') {
        container.style.display = 'block';
        templateContainer.style.display = 'block';
        select.required = true;
        if (templateSelect) {
            templateSelect.required = true;
        }
        select.innerHTML = '<option value="">Loading...</option>';
        
        fetch('/projects/' + projectId + '/final-screenings/available-models?tipo=' + encodeURIComponent(tipo), {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                select.innerHTML = '<option value="">Select AI Model</option>';
                data.data.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model.id;
                    option.textContent = model.name;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No models available</option>';
            }
        })
        .catch(error => {
            console.error('Error loading models:', error);
            select.innerHTML = '<option value="">Error loading models</option>';
        });
        
        // Load templates
        loadTemplatesForScreening();
    }
}

function loadTemplatesForScreening() {
    const select = document.getElementById('screening_template_id');
    const projectId = {{ $project->id }};
    
    if (!select) {
        return Promise.resolve();
    }
    
    select.innerHTML = '<option value="">Loading...</option>';
    
    return fetch('/projects/' + projectId + '/final-screenings/templates', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.length > 0) {
            select.innerHTML = '<option value="">Select Template (Optional)</option>';
            data.data.forEach(template => {
                const option = document.createElement('option');
                option.value = template.id;
                option.textContent = template.name;
                select.appendChild(option);
            });
        } else {
            select.innerHTML = '<option value="">No templates available</option>';
        }
    })
    .catch(error => {
        console.error('Error loading templates:', error);
        select.innerHTML = '<option value="">Error loading templates</option>';
    });
}

function openEditInitialScreeningModal(screeningId) {
    editScreeningId = screeningId;
    const projectId = {{ $project->id }};
    
    fetch('/projects/' + projectId + '/final-screenings/' + screeningId, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const screening = data.data;
            document.getElementById('finalScreeningModalTitle').textContent = 'Edit Screening';
            
            // Set values but disable fields (only date can be edited)
            const nomeField = document.getElementById('screening_nome');
            const tipoField = document.getElementById('screening_tipo');
            const aiModelField = document.getElementById('screening_ai_model_id');
            const aiModelContainer = document.getElementById('screening_ai_model_container');
            const responsavelField = document.getElementById('screening_responsavel_id');
            const dataField = document.getElementById('screening_data');
            
            nomeField.value = screening.nome;
            nomeField.disabled = true;
            
            tipoField.value = screening.tipo;
            tipoField.disabled = true;
            
            // Load and show AI model if applicable
            if (screening.tipo !== 'Human') {
                loadAvailableModelsForScreening(screening.tipo);
                setTimeout(() => {
                    if (screening.ai_model_id) {
                        aiModelField.value = screening.ai_model_id;
                    }
                    aiModelField.disabled = true;
                    
                    const templateField = document.getElementById('screening_template_id');
                    const templateContainer = document.getElementById('screening_template_container');
                    if (screening.template_id) {
                        if (templateContainer) {
                            templateContainer.style.display = 'block';
                        }
                        loadTemplatesForScreening().then(() => {
                            if (templateField) {
                                templateField.value = screening.template_id;
                                templateField.disabled = true;
                            }
                        });
                    } else {
                        if (templateContainer) {
                            templateContainer.style.display = 'none';
                        }
                    }
                }, 500);
            } else {
                aiModelContainer.style.display = 'none';
                const templateContainer = document.getElementById('screening_template_container');
                if (templateContainer) {
                    templateContainer.style.display = 'none';
                }
            }
            
            responsavelField.value = screening.responsavel_id;
            responsavelField.disabled = true;
            
            // Only date field is editable
            dataField.value = screening.data;
            dataField.disabled = false;
            
            const modal = new bootstrap.Modal(document.getElementById('createFinalScreeningModal'));
            modal.show();
        }
    })
    .catch(error => {
        console.error('Error loading screening:', error);
        alert('Error loading screening data');
    });
}

function openDeleteFinalScreeningModal(screeningId, nome) {
    deleteScreeningId = screeningId;
    document.getElementById('delete-screening-nome').textContent = nome;
    const modal = new bootstrap.Modal(document.getElementById('deleteFinalScreeningModal'));
    modal.show();
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Tipo change handler - use event delegation since modal may not be in DOM yet
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'screening_tipo') {
            loadAvailableModelsForScreening(e.target.value);
        }
    });
    
    // Form submission
    const form = document.getElementById('finalScreeningForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const projectId = {{ $project->id }};
            let formData;
            
            if (editScreeningId) {
                // When editing, only send the date field
                formData = {
                    data: document.getElementById('screening_data').value,
                };
            } else {
                // When creating, send all fields
                const tipo = document.getElementById('screening_tipo').value;
                const templateId = document.getElementById('screening_template_id').value;
                
                // Validate template is required for AI types
                if ((tipo === 'AI OpenRouter' || tipo === 'AI Local') && !templateId) {
                    const errorDiv = document.getElementById('finalScreeningError');
                    errorDiv.textContent = 'Template is required for AI screenings.';
                    errorDiv.style.display = 'block';
                    return;
                }
                
                formData = {
                    nome: document.getElementById('screening_nome').value,
                    tipo: tipo,
                    ai_model_id: document.getElementById('screening_ai_model_id').value || null,
                    template_id: templateId || null,
                    responsavel_id: document.getElementById('screening_responsavel_id').value,
                    data: document.getElementById('screening_data').value,
                };
            }

            const url = editScreeningId 
                ? '/projects/' + projectId + '/final-screenings/' + editScreeningId
                : '/projects/' + projectId + '/final-screenings';
            const method = editScreeningId ? 'PUT' : 'POST';

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createFinalScreeningModal'));
                    modal.hide();
                    form.reset();
                    editScreeningId = null;
                    document.getElementById('screening_ai_model_container').style.display = 'none';
                    if (finalScreeningsTable) {
                        finalScreeningsTable.ajax.reload();
                    }
                    if (typeof showToast === 'function') {
                        showToast('success', data.message || 'Screening saved successfully');
                    }
                } else {
                    const errorDiv = document.getElementById('finalScreeningError');
                    errorDiv.textContent = data.message || 'Error saving screening';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const errorDiv = document.getElementById('finalScreeningError');
                errorDiv.textContent = 'An error occurred while saving the screening';
                errorDiv.style.display = 'block';
            });
        });
    }

    // Delete confirmation
    const confirmDelete = document.getElementById('confirmDeleteFinalScreening');
    if (confirmDelete) {
        confirmDelete.addEventListener('click', function() {
            if (!deleteScreeningId) return;

            const projectId = {{ $project->id }};
            
            fetch('/projects/' + projectId + '/final-screenings/' + deleteScreeningId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteFinalScreeningModal'));
                    modal.hide();
                    if (finalScreeningsTable) {
                        finalScreeningsTable.ajax.reload();
                    }
                    if (typeof showToast === 'function') {
                        showToast('success', data.message || 'Screening deleted successfully');
                    }
                } else {
                    alert(data.message || 'Error deleting screening');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the screening');
            });
        });
    }

        // Reset modal on close
        const modal = document.getElementById('createFinalScreeningModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function() {
                // Re-enable all fields
                document.getElementById('screening_nome').disabled = false;
                document.getElementById('screening_tipo').disabled = false;
                document.getElementById('screening_ai_model_id').disabled = false;
                const templateField = document.getElementById('screening_template_id');
                if (templateField) {
                    templateField.disabled = false;
                }
                document.getElementById('screening_responsavel_id').disabled = false;
                document.getElementById('screening_data').disabled = false;
                
                document.getElementById('finalScreeningForm').reset();
                document.getElementById('finalScreeningError').style.display = 'none';
                document.getElementById('screening_ai_model_container').style.display = 'none';
                const templateContainer = document.getElementById('screening_template_container');
                if (templateContainer) {
                    templateContainer.style.display = 'none';
                }
                document.getElementById('finalScreeningModalTitle').textContent = 'Add Screening';
                editScreeningId = null;
            });
        
        // Set defaults when modal opens
        modal.addEventListener('shown.bs.modal', function() {
            // Use 'shown' instead of 'show' to ensure modal is fully visible
            if (!editScreeningId) {
                // Only set defaults for new screenings
                loadUsersForScreening();
                const today = new Date().toISOString().split('T')[0];
                const dataField = document.getElementById('screening_data');
                if (dataField) {
                    dataField.value = today;
                }
                getNextScreeningName().then(name => {
                    const nomeField = document.getElementById('screening_nome');
                    if (nomeField) {
                        nomeField.value = name;
                    }
                });
            }
        });
    }
});
</script>
@endsection

@section('content')
<!-- Final Screenings Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-filter-line me-2"></i>Screenings
                </h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="icon-base ri ri-arrow-left-line me-1"></i>Back
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createFinalScreeningModal">
                        <i class="icon-base ri ri-add-line me-1"></i>Add Screening
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="finalScreeningsTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>AI Model</th>
                                <th>Template</th>
                                <th>Responsible</th>
                                <th>Date</th>
                                <th>Progress</th>
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

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title mb-1">{{ $project->name }}</h2>
                <div class="text-muted mb-3" style="font-size: 0.95rem;">Initial Selection</div>
                
                @if($project->description)
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Description</h6>
                    <p class="mb-0">{{ $project->description }}</p>
                </div>
                @endif
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-end gap-2 mb-3">
                    <a href="{{ route('studies.download-final-included', $project) }}" class="btn btn-primary btn-sm">
                        <i class="icon-base ri ri-file-excel-2-line me-1"></i>Download XLS
                    </a>
                </div>

                <!-- Table Controls -->
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" id="studiesTableControls">
                    <div id="studiesLength"></div>
                    <div class="ms-auto">
                        <div class="input-group">
                            <span class="input-group-text"><i class="icon-base ri ri-search-line"></i></span>
                            <input type="text" id="studiesSearch" class="form-control" placeholder="Search ..." />
                        </div>
                    </div>
                </div>
                
                <!-- Studies Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="studiesTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>DOI</th>
                                <th>Abstract</th>
                                <th>Initial Selection</th>
                                <th>Inclusion Criteria</th>
                                <th>Exclusion Criteria</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTables will populate this -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Study Modal -->
<div class="modal fade" id="createStudyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studyModalTitle">Create Study</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="studyForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="study_id" name="study_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="study_custom_id" class="form-label">Study ID <span class="text-muted">(e.g., S1, S2, or custom)</span></label>
                            <input type="text" id="study_custom_id" name="custom_id" class="form-control" placeholder="Leave empty for auto (S1, S2...)" maxlength="30">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="study_name" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" id="study_name" name="name" class="form-control" placeholder="Enter study title" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="study_year" class="form-label">Year</label>
                            <input type="number" id="study_year" name="year" class="form-control" placeholder="e.g., 2024" min="1900" max="2100">
                        </div>
                        <div class="col-md-9 mb-3">
                            <label for="study_authors" class="form-label">Authors</label>
                            <input type="text" id="study_authors" name="authors" class="form-control" placeholder="Enter authors">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="study_document_type" class="form-label">Document Type</label>
                            <input type="text" id="study_document_type" name="document_type" class="form-control" placeholder="Enter document type">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="study_journal_conference" class="form-label">Journal/Conference</label>
                            <input type="text" id="study_journal_conference" name="journal_conference" class="form-control" placeholder="Enter journal or conference">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="study_tags" class="form-label">Tags</label>
                            <textarea id="study_tags" name="tags" class="form-control" rows="2" placeholder="Enter tags"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="study_notes" class="form-label">Notes</label>
                            <textarea id="study_notes" name="notes" class="form-control" rows="2" placeholder="Enter notes"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="study_doi" class="form-label">DOI</label>
                            <input type="text" id="study_doi" name="doi" class="form-control" placeholder="Enter DOI">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="study_link" class="form-label">Link</label>
                            <input type="url" id="study_link" name="link" class="form-control" placeholder="Enter URL">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="study_keywords" class="form-label">Keywords</label>
                            <textarea id="study_keywords" name="keywords" class="form-control" rows="2" placeholder="Enter keywords"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="study_source" class="form-label">Source</label>
                            <textarea id="study_source" name="source" class="form-control" rows="2" placeholder="Enter source"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="study_abstract" class="form-label">Abstract</label>
                            <textarea id="study_abstract" name="abstract" class="form-control" rows="4" placeholder="Enter abstract"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Initial Selection</label>
                            <select id="study_status_selecao_final" name="status_selecao_final" class="form-select">
                                <option value="">N/A</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Initial selection</label>
                            <select id="study_status_selecao_final" name="status_selecao_final" class="form-select">
                                <option value="">N/A</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Final selection</label>
                            <select id="study_status_selecao_final" name="status_selecao_final" class="form-select">
                                <option value="">N/A</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Extraction</label>
                            <select id="study_status_extracao" name="status_extracao" class="form-select">
                                <option value="">N/A</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="study_pdf" class="form-label">PDF File</label>
                            <input type="file" id="study_pdf" name="pdf" class="form-control" accept=".pdf">
                            <small class="text-muted">Max size: 50MB</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Study</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                    <!-- Left side: Screenings Table -->
                    <div class="col-md-9 border-end d-flex flex-column" style="overflow-y: auto; overflow-x: hidden; min-height: 0; max-height: 100%;">
                        <div class="p-4">
                            <input type="hidden" id="study_view_id" name="study_id">
                            <div class="mb-3">
                                <span id="study-view-custom-id" class="badge bg-label-primary mb-2"></span>
                                <h5 id="study-view-name" class="mb-3"></h5>
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">Abstract</h6>
                                    <p id="study-view-abstract" class="text-muted" style="white-space: pre-wrap; line-height: 1.6;"></p>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="text-muted mb-3">Screenings</h6>
                                <div class="table-responsive">
                                    <table id="studyScreeningsTable" class="table table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>AI Model</th>
                                                <th>Template</th>
                                                <th>Responsible</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="studyScreeningsTableBody">
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">
                                                    <i class="icon-base ri ri-loader-4-line"></i> Loading...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right side: Study Information -->
                    <div class="col-md-3" style="height: 100%; overflow: auto;">
                        <div class="p-4">
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


<!-- Delete Study Modal -->
<div class="modal fade" id="deleteStudyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Study</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this study? This action cannot be undone.</p>
                <p class="mb-0"><strong id="delete-study-name"></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteStudy">Delete Study</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Excel Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Studies from Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importExcelForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="icon-base ri ri-information-line me-2"></i>
                        <strong>Instructions:</strong> Upload an Excel file (.xlsx, .xls, or .csv) with the following columns (all optional except "name"):
                        <ul class="mb-0 mt-2">
                            <li><strong>custom_id</strong> - Custom ID (e.g., S1, S2...). Leave empty for auto-generation (max 30 characters)</li>
                            <li><strong>name</strong> or <strong>title</strong> - Study title (required)</li>
                            <li><strong>year</strong> - Publication year (1900-2100)</li>
                            <li><strong>authors</strong> - Authors</li>
                            <li><strong>document_type</strong> or <strong>document type</strong> - Document type</li>
                            <li><strong>journal_conference</strong> or <strong>journal/conference</strong> - Journal or conference name</li>
                            <li><strong>tags</strong> - Tags</li>
                            <li><strong>notes</strong> - Notes</li>
                            <li><strong>doi</strong> - DOI</li>
                            <li><strong>keywords</strong> - Keywords</li>
                            <li><strong>source</strong> - Source</li>
                            <li><strong>abstract</strong> - Abstract</li>
                            <li><strong>link</strong> or <strong>url</strong> - Link/URL</li>
                            <li><strong>status_selecao_final</strong> or <strong>duplicates and exclusion criteria</strong> - Yes/No/1/0/true/false</li>
                            <li><strong>status_selecao_final</strong> or <strong>initial selection</strong> - Yes/No/1/0/true/false</li>
                            <li><strong>status_selecao_final</strong> or <strong>final selection</strong> - Yes/No/1/0/true/false</li>
                            <li><strong>status_extracao</strong> or <strong>extraction</strong> - Yes/No/1/0/true/false</li>
                        </ul>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="excel_file" class="form-label">Select Excel File</label>
                            <input type="file" id="excel_file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                            <small class="text-muted">Max size: 10MB. Supported formats: .xlsx, .xls, .csv</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="importExcelSubmitBtn">Import Studies</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Upload PDFs Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Upload PDFs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkUploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="icon-base ri ri-information-line me-2"></i>
                        <strong>Instructions:</strong> Upload multiple PDF files. Files will be automatically assigned to studies if the filename (without extension) matches the study's custom ID (e.g., S1.pdf → study with ID S1). Files that cannot be automatically assigned will be listed below for manual assignment.
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="bulk_pdfs" class="form-label">Select PDF Files</label>
                            <input type="file" id="bulk_pdfs" name="pdfs[]" class="form-control" accept=".pdf" multiple required>
                            <small class="text-muted">You can select multiple files. Max size per file: 50MB</small>
                        </div>
                    </div>
                    <div id="uploadResults" style="display: none;">
                        <hr>
                        <h6 class="mb-3">Upload Results</h6>
                        <div id="assignedFiles" class="mb-3">
                            <h6 class="text-success">✓ Automatically Assigned</h6>
                            <ul id="assignedFilesList" class="list-group"></ul>
                        </div>
                        <div id="unassignedFiles" class="mb-3">
                            <h6 class="text-warning">⚠ Needs Manual Assignment</h6>
                            <div id="unassignedFilesList"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="bulkUploadSubmitBtn">Upload PDFs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create/Edit Final Screening Modal -->
<div class="modal fade" id="createFinalScreeningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="finalScreeningModalTitle">Add Screening</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="finalScreeningForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="screening_nome" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="screening_nome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="screening_tipo" class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="screening_tipo" name="tipo" required>
                            <option value="">Select Type</option>
                            <option value="Human">Human</option>
                            <option value="AI OpenRouter">AI OpenRouter</option>
                            <option value="AI Local">AI Local</option>
                        </select>
                    </div>
                    <div class="mb-3" id="screening_ai_model_container" style="display: none;">
                        <label for="screening_ai_model_id" class="form-label">AI Model <span class="text-danger">*</span></label>
                        <select class="form-select" id="screening_ai_model_id" name="ai_model_id">
                            <option value="">Select AI Model</option>
                        </select>
                    </div>
                    <div class="mb-3" id="screening_template_container" style="display: none;">
                        <label for="screening_template_id" class="form-label">Template <span class="text-danger">*</span></label>
                        <select class="form-select" id="screening_template_id" name="template_id" required>
                            <option value="">Select Template</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="screening_responsavel_id" class="form-label">Responsible <span class="text-danger">*</span></label>
                        <select class="form-select" id="screening_responsavel_id" name="responsavel_id" required>
                            <option value="">Select User</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="screening_data" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="screening_data" name="data" required>
                    </div>
                    <div id="finalScreeningError" class="alert alert-danger" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Final Screening Modal -->
<div class="modal fade" id="deleteFinalScreeningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Screening</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this screening? This action cannot be undone.</p>
                <p class="mb-0"><strong id="delete-screening-nome"></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteFinalScreening">Delete</button>
            </div>
        </div>
    </div>
</div>

@include('content.projects.partials.screening-output-modal')

@endsection

@extends('layouts/contentNavbarLayout')

@section('title', 'Templates')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/responsive.bootstrap5.min.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/jquery/jquery.min.js') }}"></script>
@endsection

@section('page-script')
<script>
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

    function initTemplatesTable(tableId, templateType) {
        if (typeof jQuery === 'undefined') {
            console.error('jQuery is not available!');
            return;
        }
        if (typeof jQuery.fn.DataTable === 'undefined' && typeof jQuery.fn.dataTable === 'undefined') {
            console.error('DataTables is not loaded!');
            return;
        }

        const $table = jQuery('#' + tableId);
        
        const tableVar = templateType === 'initial' ? 'templatesTableInitial' : 
                        templateType === 'final' ? 'templatesTableFinal' : 
                        'templatesTableExtraction';
        window[tableVar] = $table.DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: '/templates',
                type: 'GET',
                data: function(d) {
                    d.type = templateType;
                },
                headers: {
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                dataSrc: function(json) {
                    return json.data || [];
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTables AJAX error:', error, thrown);
                }
            },
            columns: [
                { data: 'name', name: 'name', orderable: true, searchable: true, width: '25%' },
                { data: 'template', name: 'template', orderable: false, searchable: true, width: '25%' },
                { data: 'created_at', name: 'created_at', orderable: true, searchable: false, width: '25%' },
                { data: 'id', name: 'actions', orderable: false, searchable: false, width: '25%' }
            ],
            columnDefs: [
                {
                    targets: 1,
                    render: function(data, type, row) {
                        if (!data) return 'N/A';
                        const text = String(data);
                        const truncated = text.length > 100 ? text.substring(0, 100) + '...' : text;
                        return '<span title="' + text.replace(/"/g, '&quot;') + '">' + truncated + '</span>';
                    }
                },
                {
                    targets: 3,
                    render: (function(templateType) {
                        return function(data, type, row) {
                            const rowType = row.type || templateType;
                            return '<button type="button" class="btn btn-sm btn-label-primary me-1" onclick="openEditTemplateModal(' + data + ', \'' + rowType + '\')">' +
                                   '<i class="icon-base ri ri-pencil-line me-1"></i>Edit</button>' +
                                   '<button type="button" class="btn btn-sm btn-label-danger" onclick="openDeleteTemplateModal(' + data + ', \'' + (row.name || '').replace(/'/g, "\\'") + '\', \'' + rowType + '\')">' +
                                   '<i class="icon-base ri ri-delete-bin-line me-1"></i>Delete</button>';
                        };
                    })(templateType)
                }
            ],
            order: [[2, 'desc']],
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
    }


    ensureJQuery()
        .then(() => ensureDataTables())
        .then(() => {
            setTimeout(() => {
                initTemplatesTable('templatesTableInitial', 'initial');
                initTemplatesTable('templatesTableFinal', 'final');
                initTemplatesTable('templatesTableExtraction', 'extraction');
            }, 100);
        })
        .catch((error) => {
            console.error('Failed to load jQuery/DataTables:', error);
        });

    let editTemplateId = null;
    let editTemplateType = 'initial';
    let deleteTemplateId = null;
    let deleteTemplateType = 'initial';

    window.openEditTemplateModal = function(id, templateType) {
        editTemplateId = id;
        editTemplateType = templateType || 'initial';
        let tableVar;
        if (editTemplateType === 'initial') {
            tableVar = 'templatesTableInitial';
        } else if (editTemplateType === 'final') {
            tableVar = 'templatesTableFinal';
        } else if (editTemplateType === 'extraction') {
            tableVar = 'templatesTableExtraction';
        } else {
            tableVar = 'templatesTableInitial';
        }
        const row = window[tableVar].row(function(idx, data) {
            return data.id === id;
        }).data();

        if (row) {
            document.getElementById('template_name').value = row.name || '';
            document.getElementById('template_content').value = row.template || '';
            const templateType = row.type || editTemplateType;
            document.getElementById('template_type_hidden').value = templateType;
            document.getElementById('templateModalTitle').textContent = 'Edit Template';
            updateAvailableVariables(templateType);
            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }
    };

    window.openDeleteTemplateModal = function(id, name, templateType) {
        deleteTemplateId = id;
        deleteTemplateType = templateType || 'initial';
        document.getElementById('delete-template-name').textContent = name || 'this template';
        const modal = new bootstrap.Modal(document.getElementById('deleteTemplateModal'));
        modal.show();
    };

    // Create/Edit form submission
    const templateForm = document.getElementById('templateForm');
    if (templateForm) {
        templateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                name: document.getElementById('template_name').value,
                template: document.getElementById('template_content').value,
                type: document.getElementById('template_type_hidden').value,
            };

            const url = editTemplateId ? '/templates/' + editTemplateId : '/templates';
            const method = editTemplateId ? 'PUT' : 'POST';

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
                    const modal = bootstrap.Modal.getInstance(document.getElementById('templateModal'));
                    modal.hide();
                    // Reload the appropriate table based on template type
                    const savedTemplateType = formData.type;
                    if (savedTemplateType === 'initial' && window.templatesTableInitial) {
                        window.templatesTableInitial.ajax.reload();
                    } else if (savedTemplateType === 'final' && window.templatesTableFinal) {
                        window.templatesTableFinal.ajax.reload();
                    } else if (savedTemplateType === 'extraction' && window.templatesTableExtraction) {
                        window.templatesTableExtraction.ajax.reload();
                    }
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message || 'Template saved successfully', 'success');
                    } else {
                        alert(data.message || 'Template saved successfully');
                    }
                } else {
                    const errorDiv = document.getElementById('templateError');
                    errorDiv.textContent = data.message || 'Error saving template';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const errorDiv = document.getElementById('templateError');
                errorDiv.textContent = 'An error occurred while saving the template';
                errorDiv.style.display = 'block';
            });
        });
    }

    // Delete confirmation
    const confirmDelete = document.getElementById('confirmDeleteTemplate');
    if (confirmDelete) {
        confirmDelete.addEventListener('click', function() {
            if (!deleteTemplateId) return;
            
            fetch('/templates/' + deleteTemplateId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteTemplateModal'));
                    modal.hide();
                    // Reload both tables to ensure consistency
                    if (window.templatesTableInitial) {
                        window.templatesTableInitial.ajax.reload();
                    }
                    if (window.templatesTableFinal) {
                        window.templatesTableFinal.ajax.reload();
                    }
                    if (window.templatesTableExtraction) {
                        window.templatesTableExtraction.ajax.reload();
                    }
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message || 'Template deleted successfully', 'success');
                    } else {
                        alert(data.message || 'Template deleted successfully');
                    }
                } else {
                    alert(data.message || 'Error deleting template');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the template');
            });
        });
    }

    window.openAddTemplateModal = function(type) {
        editTemplateId = null;
        editTemplateType = type || 'initial';
        document.getElementById('templateForm').reset();
        document.getElementById('templateError').style.display = 'none';
        const templateType = type || 'initial';
        document.getElementById('template_type_hidden').value = templateType;
        
        const titleMap = {
            'initial': 'Initial',
            'final': 'Final',
            'extraction': 'Extraction'
        };
        document.getElementById('templateModalTitle').textContent = 'Add ' + (titleMap[templateType] || 'Initial') + ' Template';
        updateAvailableVariables(templateType);
        const modal = new bootstrap.Modal(document.getElementById('templateModal'));
        modal.show();
    };

    function updateAvailableVariables(templateType) {
        const isExtraction = templateType === 'extraction';
        document.getElementById('templateOutputScreening').style.display = isExtraction ? 'none' : '';
        document.getElementById('templateOutputExtraction').style.display = isExtraction ? '' : 'none';
        updateJsondataWarning();

        const variablesList = document.getElementById('availableVariablesList');
        if (templateType === 'final') {
            variablesList.innerHTML = '<li><code>[[id]]</code> - Study ID</li>' +
                                     '<li><code>[[title]]</code> - Study title</li>' +
                                     '<li><code>[[fullstudy]]</code> - Full text of the study (Markdown generated from its PDF)</li>' +
                                     '<li><code>[[inclusioncriteria]]</code> - Inclusion criteria</li>' +
                                     '<li><code>[[exclusioncriteria]]</code> - Exclusion criteria</li>';
        } else if (templateType === 'extraction') {
            variablesList.innerHTML = '<li><code>[[id]]</code> - Study ID</li>' +
                                     '<li><code>[[title]]</code> - Study title</li>' +
                                     '<li><code>[[fullstudy]]</code> - Full text of the study (Markdown generated from its PDF)</li>' +
                                     '<li><code>[[jsondata]]</code> - The JSON to answer (one key per extraction field plus <code>_evidence</code>) and its instructions</li>';
        } else {
            variablesList.innerHTML = '<li><code>[[id]]</code> - Study ID</li>' +
                                     '<li><code>[[title]]</code> - Study title</li>' +
                                     '<li><code>[[abstract]]</code> - Study abstract</li>' +
                                     '<li><code>[[inclusioncriteria]]</code> - Inclusion criteria</li>' +
                                     '<li><code>[[exclusioncriteria]]</code> - Exclusion criteria</li>';
        }
    }


    // Extraction templates need [[jsondata]] for the fields and the passages
    function updateJsondataWarning() {
        const isExtraction = document.getElementById('template_type_hidden').value === 'extraction';
        const content = document.getElementById('template_content').value;
        document.getElementById('templateJsondataWarning').style.display =
            isExtraction && content.trim() !== '' && !/\[\[jsondata\]\]/i.test(content) ? '' : 'none';
    }
    document.getElementById('template_content').addEventListener('input', updateJsondataWarning);

    // Reset modal on close
    const modal = document.getElementById('templateModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('templateForm').reset();
            document.getElementById('templateError').style.display = 'none';
            document.getElementById('templateModalTitle').textContent = 'Add Template';
            document.getElementById('template_type_hidden').value = 'initial';
            updateAvailableVariables('initial');
            editTemplateId = null;
            editTemplateType = 'initial';
        });
    }

});
</script>
@endsection

@section('content')
<!-- Initial Templates -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Initial Templates</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddTemplateModal('initial')">
                    <i class="icon-base ri ri-add-line me-1"></i>Add Initial Template
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="templatesTableInitial" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Template</th>
                                <th>Created At</th>
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

<!-- Final Templates -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Final Templates</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddTemplateModal('final')">
                    <i class="icon-base ri ri-add-line me-1"></i>Add Final Template
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="templatesTableFinal" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Template</th>
                                <th>Created At</th>
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

<!-- Extraction Templates -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Extraction Templates</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddTemplateModal('extraction')">
                    <i class="icon-base ri ri-add-line me-1"></i>Add Extraction Template
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="templatesTableExtraction" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Template</th>
                                <th>Created At</th>
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

<style>
    /* Template modal: name and template on the left (70%), help on the right (30%) */
    @media (min-width: 992px) {
        #templateModal .template-main { flex: 0 0 70%; max-width: 70%; height: 100%; }
        #templateModal .template-side { flex: 0 0 30%; max-width: 30%; height: 100%; }
    }
    #templateModal .template-side-scroll { overflow-y: auto; }
    @media (max-width: 991.98px) {
        #templateModal .modal-body { overflow-y: auto !important; }
        #templateModal #template_content { min-height: 50vh !important; }
    }
</style>
<!-- Create/Edit Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document" style="width: 95vw; max-width: 95vw; height: 95vh; margin: 2.5vh auto;">
        <div class="modal-content" style="height: 95vh; max-height: 95vh;">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">Add Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="templateForm" class="d-flex flex-column flex-grow-1" style="min-height: 0;">
                <div class="modal-body" style="overflow: hidden; display: flex; flex-direction: column; min-height: 0;">
                    <div id="templateError" class="alert alert-danger" style="display: none;"></div>
                    <input type="hidden" id="template_type_hidden" name="type" value="initial">
                    <div class="row g-4 flex-grow-1" style="min-height: 0;">
                        <!-- Left (70%): name and template -->
                        <div class="col-12 template-main d-flex flex-column" style="min-height: 0;">
                            <div class="row">
                        <div class="col-12 mb-3">
                            <label for="template_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="template_name" name="name" class="form-control" placeholder="Enter template name" required maxlength="255">
                        </div>
                    </div>
                            <div class="d-flex flex-column flex-grow-1" style="min-height: 0;">
                                <label for="template_content" class="form-label">Template <span class="text-danger">*</span></label>
                                <textarea id="template_content" name="template" class="form-control flex-grow-1" placeholder="Enter template content" required style="min-height: 300px; resize: none;"></textarea>
                            </div>
                        </div>
                        <!-- Right (30%): variables and expected output -->
                        <div class="col-12 template-side template-side-scroll">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="alert alert-info">
                                <i class="icon-base ri ri-information-line me-2"></i>
                                <strong>Available Variables:</strong> You can use the following variables in your template, which will be replaced with actual values:
                                <ul id="availableVariablesList" class="mb-0 mt-2">
                                    <li><code>[[id]]</code> - Study ID</li>
                                    <li><code>[[title]]</code> - Study title</li>
                                    <li><code>[[abstract]]</code> - Study abstract</li>
                                    <li><code>[[inclusioncriteria]]</code> - Inclusion criteria</li>
                                    <li><code>[[exclusioncriteria]]</code> - Exclusion criteria</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="templateOutputScreening">
                        <div class="col-12 mb-3">
                            <div class="alert alert-info">
                                <i class="icon-base ri ri-information-line me-2"></i>
                                <strong>Output:</strong> The JSON response must contain at minimum the following fields (other fields may be added, but these are required):
                                <pre class="mb-0 mt-2 bg-white p-2 rounded" style="font-size: 0.875rem;"><code>{
  "id": "Study ID",
  "status": 1, // integer: 0 = excluded, 1 = included
  "justification": "Brief explanation for including or excluding the study, ensuring the word count remains under the 30 words limit for conciseness and clarity."
}</code></pre>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="templateOutputExtraction" style="display: none;">
                        <div class="col-12 mb-3">
                            <div id="templateJsondataWarning" class="alert alert-warning" style="display: none;">
                                <i class="icon-base ri ri-error-warning-line me-2"></i>
                                This template does not contain <code>[[jsondata]]</code>. Without it the AI does not receive the extraction
                                fields nor the request for the passages, so answers may not be saved and nothing is highlighted in the article.
                            </div>
                            <div class="alert alert-info mb-0">
                                <i class="icon-base ri ri-information-line me-2"></i>
                                <strong>Output:</strong> The JSON response has one key per extraction field of the project, named by the
                                field ID (<code>ED1</code>, <code>ED2</code>, ...), with its answer, and an <code>_evidence</code> key with, for
                                each field ID, the passages of the study the answer came from, copied word for word:
                                <pre class="mb-2 mt-2 bg-white p-2 rounded" style="font-size: 0.875rem;"><code>{
  "ED1": "2022",
  "ED2": "Journal article",
  "_evidence": {
    "ED1": ["passage copied word for word from the study", "..."],
    "ED2": []
  }
}</code></pre>
                                <ul class="mb-0 ps-3">
                                    <li>You do not need to write this structure: <code>[[jsondata]]</code> is replaced with it, built from the
                                        project's extraction fields, together with the instructions for each field (its ID, name and expected
                                        answer) and for the passages. Keep <code>[[jsondata]]</code> in the template and ask for a JSON-only answer.</li>
                                    <li>If the template shows an example of the answer, use the field IDs as keys and include
                                        <code>_evidence</code>, so the example does not contradict the instructions.</li>
                                    <li>The passages are highlighted, one color per field, in the <strong>Article (Markdown)</strong> tab of the
                                        extraction study window. An empty list means the answer is not stated in the study.</li>
                                    <li>Up to 5 passages per field are kept; passages that do not match the article text are listed but not highlighted.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Template Modal -->
<div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete-template-name"></strong>? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteTemplate">Delete Template</button>
            </div>
        </div>
    </div>
</div>

@endsection

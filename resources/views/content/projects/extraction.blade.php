@extends('layouts/contentNavbarLayout')

@section('title', $project->name . ' - Extraction')

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
    .extraction-status-spin {
      display: inline-block;
      animation: extraction-status-spin 1s linear infinite;
    }
    @keyframes extraction-status-spin {
      to { transform: rotate(360deg); }
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
              d.filter_final_included = true;
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
          columns: [{
              data: 'custom_id',
              name: 'custom_id',
              orderable: true,
              searchable: true
            },
            {
              data: 'name',
              name: 'name',
              orderable: true,
              searchable: true
            },
            {
              data: 'doi',
              name: 'doi',
              orderable: true,
              searchable: true
            },
            {
              data: 'abstract',
              name: 'abstract',
              orderable: false,
              searchable: true
            }
          ],
          columnDefs: [{
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
                  '<div class="avatar me-2 flex-shrink-0" style="cursor: pointer;" onclick="openStudyViewModal(' +
                  row.id + ')">' +
                  '<span class="avatar-initial rounded-circle ' + avatarBg + '">' +
                  '<i class="icon-base ri ' + iconClass + '"></i>' +
                  '</span>' +
                  '</div>' +
                  '<a href="javascript:void(0);" onclick="openStudyViewModal(' + row.id +
                  ')" class="text-primary fw-semibold" style="cursor: pointer; text-decoration: none;">' +
                  title + '</a>' +
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
                return '<a href="' + doiUrl +
                  '" target="_blank" rel="noopener" class="text-primary" style="text-decoration: none;">' +
                  doiText + '</a>';
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
                return '<span class="d-inline-block" style="max-width: 420px; white-space: normal;">' + text +
                  '</span>';
              }
            },
          ],
          order: [
            [0, 'asc']
          ],
          pageLength: 15,
          lengthMenu: [
            [10, 15, 25, 50, 100],
            [10, 15, 25, 50, 100]
          ],
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
          document.getElementById('study_status_duplicados_exclusao').value = study.status_duplicados_exclusao ===
            null ? '' : (study.status_duplicados_exclusao ? '1' : '0');
          document.getElementById('study_status_selecao_inicial').value = study.status_selecao_inicial === null ? '' :
            (study.status_selecao_inicial ? '1' : '0');
          document.getElementById('study_status_selecao_final').value = study.status_selecao_final === null ? '' : (
            study.status_selecao_final ? '1' : '0');
          document.getElementById('study_status_extracao').value = study.status_extracao === null ? '' : (study
            .status_extracao ? '1' : '0');
          document.getElementById('study_pdf').value = '';
          const modal = new bootstrap.Modal(document.getElementById('createStudyModal'));
          modal.show();
        }

        // Open study view modal
        window.openStudyViewModal = function(studyId) {
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
                  displayStudyInModal(foundStudy);
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
            return;
          }
          displayStudyInModal(study);
        };

        function displayStudyInModal(study) {
          document.getElementById('study_view_id').value = study.id;
          document.getElementById('study-view-name').textContent = study.name || 'N/A';
          const abstractText = (study.abstract || '').trim() || 'No abstract available';
          document.getElementById('study-view-abstract').textContent = abstractText;
          document.getElementById('study_view_duplicados_exclusao').value = study.status_duplicados_exclusao ===
            null ? '' : (study.status_duplicados_exclusao ? '1' : '0');
          document.getElementById('study_view_selecao_inicial').value = study.status_selecao_inicial === null ? '' : (
            study.status_selecao_inicial ? '1' : '0');
          document.getElementById('study_view_selecao_final').value = study.status_selecao_final === null ? '' : (
            study.status_selecao_final ? '1' : '0');
          document.getElementById('study_view_extracao').value = study.status_extracao === null ? '' : (study
            .status_extracao ? '1' : '0');
          const pdfPreview = document.getElementById('study-pdf-preview');
          if (study.pdf_path) {
            const pdfUrl = '/storage/s3/' + encodeURIComponent(study.pdf_path);
            pdfPreview.style.padding = '0';
            pdfPreview.style.overflow = 'hidden';
            pdfPreview.style.backgroundColor = 'transparent';
            pdfPreview.innerHTML = '<embed src="' + pdfUrl +
              '#toolbar=0&view=FitH" type="application/pdf" style="width: 100%; height: 100%; border: none; flex: 1; min-height: 0;" />';
          } else {
            pdfPreview.style.padding = '0';
            pdfPreview.style.backgroundColor = '#f5f6f8';
            pdfPreview.innerHTML =
              '<div class="d-flex align-items-center justify-content-center h-100 w-100"><div class="text-center text-muted p-5"><i class="icon-base ri ri-file-pdf-line icon-3x mb-3"></i><p class="mb-0">No PDF available for this study</p></div></div>';
          }
          const modal = new bootstrap.Modal(document.getElementById('studyViewModal'));
          modal.show();
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
            const statusDuplicadosExclusao = document.getElementById('study_status_duplicados_exclusao').value;
            formData.append('status_duplicados_exclusao', statusDuplicadosExclusao === '' ? '' : (
              statusDuplicadosExclusao === '1' ? '1' : '0'));
            const statusInitial = document.getElementById('study_status_selecao_inicial').value;
            formData.append('status_selecao_inicial', statusInitial === '' ? '' : (statusInitial === '1' ? '1' :
              '0'));
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
            const formData = {
              status_duplicados_exclusao: document.getElementById('study_view_duplicados_exclusao').value ===
                '' ? null : (document.getElementById('study_view_duplicados_exclusao').value === '1'),
              status_selecao_inicial: document.getElementById('study_view_selecao_inicial').value === '' ?
                null : (document.getElementById('study_view_selecao_inicial').value === '1'),
              status_selecao_final: document.getElementById('study_view_selecao_final').value === '' ? null : (
                document.getElementById('study_view_selecao_final').value === '1'),
              status_extracao: document.getElementById('study_view_extracao').value === '' ? null : (document
                .getElementById('study_view_extracao').value === '1'),
              _token: document.querySelector('meta[name="csrf-token"]').content
            };
            const submitButton = saveStudyViewBtn;
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
            fetch('/projects/' + projectId + '/studies/' + studyId, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': formData._token,
                  'X-HTTP-Method-Override': 'PUT',
                  'Accept': 'application/json'
                },
                body: JSON.stringify({
                  status_duplicados_exclusao: formData.status_duplicados_exclusao,
                  status_selecao_inicial: formData.status_selecao_inicial,
                  status_selecao_final: formData.status_selecao_final,
                  status_extracao: formData.status_extracao
                })
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  const modal = bootstrap.Modal.getInstance(document.getElementById('studyViewModal'));
                  modal.hide();
                  if (typeof window.showToast === 'function') {
                    window.showToast('Study updated successfully!', 'success');
                  } else {
                    alert('Study updated successfully!');
                  }
                  loadStudies();
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
        @include('content.projects.partials.bulk_upload_js')
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
const submitButton = document.getElementById('bulkUploadSubmitBtn');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Uploading...';
            window.uploadPdfsInBatches(projectId, pdfFiles, (done, total) => {
                submitButton.textContent = `Uploading... (${done}/${total})`;
            })
              .then(data => {
                if (data.success) {
                  document.getElementById('uploadResults').style.display = 'block';
                  const assignedList = document.getElementById('assignedFilesList');
                  assignedList.innerHTML = '';
                  if (data.results.assigned.length > 0) {
                    data.results.assigned.forEach(item => {
                      const li = document.createElement('li');
                      li.className = 'list-group-item';
                      li.innerHTML =
                        `<strong>${item.filename}</strong> → Assigned to <strong>${item.study_custom_id}</strong> (${item.study_name || 'N/A'})`;
                      assignedList.appendChild(li);
                    });
                  } else {
                    assignedList.innerHTML =
                      '<li class="list-group-item text-muted">No files were automatically assigned</li>';
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
                    unassignedList.innerHTML =
                      '<p class="text-muted">All files were automatically assigned!</p>';
                  }
                  loadStudies();
                  if (typeof window.showToast === 'function') {
                    window.showToast(
                      `Upload completed: ${data.results.assigned.length} assigned, ${data.results.unassigned.length} need manual assignment`,
                      'success');
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
        .catch((error) => {
          console.error('Failed to load jQuery/DataTables:', error);
        });
    });
  </script>
@endsection

@section('content')
  <!-- Data to Extract Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Data to Extract</h5>
          <button type="button" class="btn btn-primary btn-sm" onclick="openAddExtractionDataModal()">
            <i class="icon-base ri ri-add-line me-1"></i>Add Data for extraction
          </button>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="extractionDataTable" class="table table-striped table-bordered">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Value</th>
                  <th>Note</th>
                  <th>Actions</th>
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

  <!-- Extraction Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Extraction</h5>
          <button type="button" class="btn btn-primary btn-sm" onclick="openAddExtractionModal()">
            <i class="icon-base ri ri-add-line me-1"></i> Extraction
          </button>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="extractionsTable" class="table table-striped table-bordered">
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
          <div class="text-muted mb-3" style="font-size: 0.95rem;">Extraction</div>

          @if ($project->description)
            <div class="mb-4">
              <h6 class="text-muted mb-2">Description</h6>
              <p class="mb-0">{{ $project->description }}</p>
            </div>
          @endif

          <!-- Action Buttons -->
          <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-info btn-sm me-2" data-bs-toggle="modal"
              data-bs-target="#importExcelModal">
              <i class="icon-base ri ri-file-excel-2-line me-1"></i>Import Excel/CSV
            </button>
            <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal"
              data-bs-target="#bulkUploadModal">
              <i class="icon-base ri ri-upload-cloud-2-line me-1"></i>Bulk Upload PDFs
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
              data-bs-target="#createStudyModal">
              <i class="icon-base ri ri-add-line me-1"></i>Add Study
            </button>
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
                <label for="study_custom_id" class="form-label">Study ID <span class="text-muted">(e.g., S1, S2, or
                    custom)</span></label>
                <input type="text" id="study_custom_id" name="custom_id" class="form-control"
                  placeholder="Leave empty for auto (S1, S2...)" maxlength="30">
              </div>
              <div class="col-md-6 mb-3">
                <label for="study_name" class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" id="study_name" name="name" class="form-control"
                  placeholder="Enter study title" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3 mb-3">
                <label for="study_year" class="form-label">Year</label>
                <input type="number" id="study_year" name="year" class="form-control" placeholder="e.g., 2024"
                  min="1900" max="2100">
              </div>
              <div class="col-md-9 mb-3">
                <label for="study_authors" class="form-label">Authors</label>
                <input type="text" id="study_authors" name="authors" class="form-control"
                  placeholder="Enter authors">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="study_document_type" class="form-label">Document Type</label>
                <input type="text" id="study_document_type" name="document_type" class="form-control"
                  placeholder="Enter document type">
              </div>
              <div class="col-md-6 mb-3">
                <label for="study_journal_conference" class="form-label">Journal/Conference</label>
                <input type="text" id="study_journal_conference" name="journal_conference" class="form-control"
                  placeholder="Enter journal or conference">
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
                <label class="form-label">duplicates and EC</label>
                <select id="study_status_duplicados_exclusao" name="status_duplicados_exclusao" class="form-select">
                  <option value="">N/A</option>
                  <option value="1">Yes</option>
                  <option value="0">No</option>
                </select>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Initial selection</label>
                <select id="study_status_selecao_inicial" name="status_selecao_inicial" class="form-select">
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
        <div class="modal-body p-0"
          style="height: calc(90vh - 120px); overflow: hidden; display: flex; flex-direction: column;">
          <div class="row g-0 flex-grow-1" style="min-height: 0;">
            <!-- Left side: PDF Preview -->
            <div class="col-md-9 border-end d-flex flex-column" style="overflow: hidden; min-height: 0;">
              <div id="study-pdf-preview"
                style="flex: 1; overflow: hidden; padding: 0; min-height: 0; display: flex; align-items: stretch; background-color: #f5f6f8;">
                <div class="d-flex align-items-center justify-content-center h-100 w-100">
                  <div class="text-center text-muted p-5">
                    <i class="icon-base ri ri-file-pdf-line icon-3x mb-3"></i>
                    <p class="mb-0">No PDF available for this study</p>
                  </div>
                </div>
              </div>
            </div>
            <!-- Right side: Study Information -->
            <div class="col-md-3" style="height: 100%; overflow: auto;">
              <div class="p-4">
                <h5 id="study-view-name" class="mb-3"></h5>
                <div class="mb-4">
                  <h6 class="text-muted mb-2">Abstract</h6>
                  <p id="study-view-abstract" class="text-muted" style="white-space: pre-wrap; line-height: 1.6;"></p>
                </div>
                <form id="studyViewForm">
                  <input type="hidden" id="study_view_id" name="study_id">
                  <div class="row mb-3">
                    <div class="col-12">
                      <label for="study_view_duplicados_exclusao" class="form-label">duplicates and EC</label>
                      <select id="study_view_duplicados_exclusao" name="status_duplicados_exclusao"
                        class="form-select">
                        <option value="">N/A</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                      </select>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-12">
                      <label for="study_view_selecao_inicial" class="form-label">Initial selection</label>
                      <select id="study_view_selecao_inicial" name="status_selecao_inicial" class="form-select">
                        <option value="">N/A</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                      </select>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-12">
                      <label for="study_view_selecao_final" class="form-label">Final selection</label>
                      <select id="study_view_selecao_final" name="status_selecao_final" class="form-select">
                        <option value="">N/A</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                      </select>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-12">
                      <label for="study_view_extracao" class="form-label">Extraction</label>
                      <select id="study_view_extracao" name="status_extracao" class="form-select">
                        <option value="">N/A</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                      </select>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="saveStudyViewBtn">Save Changes</button>
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
              <strong>Instructions:</strong> Upload an Excel file (.xlsx, .xls, or .csv) with the following columns (all
              optional except "name"):
              <ul class="mb-0 mt-2">
                <li><strong>custom_id</strong> - Custom ID (e.g., S1, S2...). Leave empty for auto-generation (max 30
                  characters)</li>
                <li><strong>name</strong> or <strong>title</strong> - Study title (required)</li>
                <li><strong>year</strong> - Publication year (1900-2100)</li>
                <li><strong>authors</strong> - Authors</li>
                <li><strong>document_type</strong> or <strong>document type</strong> - Document type</li>
                <li><strong>journal_conference</strong> or <strong>journal/conference</strong> - Journal or conference
                  name</li>
                <li><strong>tags</strong> - Tags</li>
                <li><strong>notes</strong> - Notes</li>
                <li><strong>doi</strong> - DOI</li>
                <li><strong>keywords</strong> - Keywords</li>
                <li><strong>source</strong> - Source</li>
                <li><strong>abstract</strong> - Abstract</li>
                <li><strong>link</strong> or <strong>url</strong> - Link/URL</li>
                <li><strong>status_duplicados_exclusao</strong> or <strong>duplicates and exclusion criteria</strong> -
                  Yes/No/1/0/true/false</li>
                <li><strong>status_selecao_inicial</strong> or <strong>initial selection</strong> - Yes/No/1/0/true/false
                </li>
                <li><strong>status_selecao_final</strong> or <strong>final selection</strong> - Yes/No/1/0/true/false</li>
                <li><strong>status_extracao</strong> or <strong>extraction</strong> - Yes/No/1/0/true/false</li>
              </ul>
            </div>
            <div class="row">
              <div class="col-12 mb-3">
                <label for="excel_file" class="form-label">Select Excel File</label>
                <input type="file" id="excel_file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv"
                  required>
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
              <strong>Instructions:</strong> Upload multiple PDF files. Files will be automatically assigned to studies if
              the filename (without extension) matches the study's custom ID (e.g., S1.pdf → study with ID S1). Files that
              cannot be automatically assigned will be listed below for manual assignment.
            </div>
            <div class="row">
              <div class="col-12 mb-3">
                <label for="bulk_pdfs" class="form-label">Select PDF Files</label>
                <input type="file" id="bulk_pdfs" name="pdfs[]" class="form-control" accept=".pdf" multiple
                  required>
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

  <!-- Extraction Data Modal -->
  <div class="modal fade" id="extractionDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="extractionDataModalTitle">Add Extraction Data</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="extractionDataForm">
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-12">
                <label for="extraction_data_custom_id" class="form-label">ID <small class="text-muted">(Auto-generated
                    if empty)</small></label>
                <input type="text" id="extraction_data_custom_id" name="custom_id" class="form-control"
                  maxlength="20" placeholder="ED1, ED2, etc.">
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-12">
                <label for="extraction_data_name" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" id="extraction_data_name" name="name" class="form-control" required
                  placeholder="e.g., title, author, source, source type">
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-12">
                <label for="extraction_data_value" class="form-label">Value</label>
                <textarea id="extraction_data_value" name="value" class="form-control" rows="3"
                  placeholder="Free text, YYYY, List of benefits, Yes/No, Number, etc."></textarea>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-12">
                <label for="extraction_data_note" class="form-label">Note</label>
                <textarea id="extraction_data_note" name="note" class="form-control" rows="2"
                  placeholder="Observations to guide data collection"></textarea>
              </div>
            </div>

            <!-- Help Box -->
            <div class="alert alert-info mb-0" role="alert">
              <h6 class="alert-heading mb-2"><i class="icon-base ri ri-information-line me-1"></i>Examples:</h6>
              <div class="mb-2">
                <strong>Name:</strong> title, author, source, source type, "What benefits of the approach are reported?",
                "What type of data was collected?", "Did the study suggest areas for future research?"
              </div>
              <div class="mb-2">
                <strong>Value:</strong> Free text, YYYY, "List of benefits", "Observations, Interviews, Questionnaire,
                Recordings, Not reported, Other", Yes/No, Number
              </div>
              <div class="mb-0">
                <strong>Note:</strong> List of all authors (Lastname, Firstname). Separate with semicolons.
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Extraction Data Modal -->
  <div class="modal fade" id="deleteExtractionDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Delete Extraction Data</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this extraction data? This action cannot be undone.</p>
          <p class="mb-0"><strong id="delete-extraction-data-name"></strong></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmDeleteExtractionData">Delete</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Extraction Data CRUD
    document.addEventListener('DOMContentLoaded', function() {
      const projectId = {{ $project->id }};
      let extractionDataTable = null;
      let editExtractionDataId = null;
      let deleteExtractionDataId = null;

      // Initialize Extraction Data DataTable
      function initExtractionDataTable() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
          console.error('jQuery or DataTables not available');
          return;
        }

        const $table = jQuery('#extractionDataTable');
        if (!$table.length) {
          console.error('Extraction Data Table not found');
          return;
        }

        extractionDataTable = $table.DataTable({
          processing: true,
          serverSide: true,
          responsive: true,
          ajax: {
            url: '/projects/' + projectId + '/extraction-data',
            type: 'GET',
            headers: {
              'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            dataSrc: function(json) {
              return json.data || [];
            },
            error: function(xhr, error, thrown) {
              console.error('Extraction Data DataTables AJAX error:', error, thrown);
            }
          },
          columns: [{
              data: 'custom_id',
              name: 'custom_id',
              orderable: true,
              searchable: true
            },
            {
              data: 'name',
              name: 'name',
              orderable: true,
              searchable: true
            },
            {
              data: 'value',
              name: 'value',
              orderable: false,
              searchable: true
            },
            {
              data: 'note',
              name: 'note',
              orderable: false,
              searchable: true
            },
            {
              data: 'id',
              name: 'actions',
              orderable: false,
              searchable: false
            }
          ],
          columnDefs: [{
              targets: 2,
              render: function(data, type, row) {
                if (!data) return '';
                const text = String(data);
                return text.length > 100 ? '<span title="' + text.replace(/"/g, '&quot;') + '">' + text
                  .substring(0, 100) + '...</span>' : text;
              }
            },
            {
              targets: 3,
              render: function(data, type, row) {
                if (!data) return '';
                const text = String(data);
                return text.length > 100 ? '<span title="' + text.replace(/"/g, '&quot;') + '">' + text
                  .substring(0, 100) + '...</span>' : text;
              }
            },
            {
              targets: 4,
              render: function(data, type, row) {
                return '<button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="openEditExtractionDataModal(' +
                  data + ')" title="Edit">' +
                  '<i class="icon-base ri ri-pencil-line"></i></button> ' +
                  '<button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="openDeleteExtractionDataModal(' +
                  data + ', \'' + (row.name || '').replace(/'/g, "\\'") + '\')" title="Delete">' +
                  '<i class="icon-base ri ri-delete-bin-line"></i></button>';
              }
            }
          ],
          order: [
            [0, 'asc']
          ],
          pageLength: 10,
          lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
          ],
          language: {
            search: "Search:",
            lengthMenu: "_MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
          }
        });
      }

      // Get next sequential ID
      function getNextExtractionDataId() {
        return fetch('/projects/' + projectId + '/extraction-data?draw=1&start=0&length=1000', {
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          })
          .then(response => response.json())
          .then(data => {
            if (data.data && Array.isArray(data.data)) {
              let maxNumber = 0;
              data.data.forEach(item => {
                if (item.custom_id && item.custom_id.match(/^ED(\d+)$/)) {
                  const number = parseInt(item.custom_id.match(/^ED(\d+)$/)[1]);
                  if (number > maxNumber) {
                    maxNumber = number;
                  }
                }
              });
              return 'ED' + (maxNumber + 1);
            }
            return 'ED1';
          })
          .catch(error => {
            console.error('Error getting next ID:', error);
            return 'ED1';
          });
      }

      // Open Add Modal
      window.openAddExtractionDataModal = function() {
        editExtractionDataId = null;
        document.getElementById('extractionDataModalTitle').textContent = 'Add Extraction Data';
        document.getElementById('extractionDataForm').reset();

        // Get and suggest next sequential ID
        getNextExtractionDataId().then(nextId => {
          document.getElementById('extraction_data_custom_id').value = nextId;
        });

        const modal = new bootstrap.Modal(document.getElementById('extractionDataModal'));
        modal.show();
      };

      // Open Edit Modal
      window.openEditExtractionDataModal = function(id) {
        editExtractionDataId = id;
        const row = extractionDataTable.row(function(idx, data) {
          return data.id === id;
        }).data();

        if (row) {
          document.getElementById('extractionDataModalTitle').textContent = 'Edit Extraction Data';
          document.getElementById('extraction_data_custom_id').value = row.custom_id || '';
          document.getElementById('extraction_data_name').value = row.name || '';
          document.getElementById('extraction_data_value').value = row.value || '';
          document.getElementById('extraction_data_note').value = row.note || '';
          const modal = new bootstrap.Modal(document.getElementById('extractionDataModal'));
          modal.show();
        }
      };

      // Open Delete Modal
      window.openDeleteExtractionDataModal = function(id, name) {
        deleteExtractionDataId = id;
        document.getElementById('delete-extraction-data-name').textContent = name || 'this extraction data';
        const modal = new bootstrap.Modal(document.getElementById('deleteExtractionDataModal'));
        modal.show();
      };

      // Form submission
      const extractionDataForm = document.getElementById('extractionDataForm');
      if (extractionDataForm) {
        extractionDataForm.addEventListener('submit', function(e) {
          e.preventDefault();

          const formData = {
            custom_id: document.getElementById('extraction_data_custom_id').value,
            name: document.getElementById('extraction_data_name').value,
            value: document.getElementById('extraction_data_value').value,
            note: document.getElementById('extraction_data_note').value,
          };

          const url = editExtractionDataId ? '/projects/' + projectId + '/extraction-data/' +
            editExtractionDataId : '/projects/' + projectId + '/extraction-data';
          const method = editExtractionDataId ? 'PUT' : 'POST';

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
                bootstrap.Modal.getInstance(document.getElementById('extractionDataModal')).hide();
                extractionDataTable.ajax.reload();
                // Show success toast
                if (window.toastr) {
                  toastr.success(data.message || 'Extraction data saved successfully');
                }
              } else {
                alert(data.message || 'Error saving extraction data');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Error saving extraction data');
            });
        });
      }

      // Delete confirmation
      const confirmDeleteExtractionData = document.getElementById('confirmDeleteExtractionData');
      if (confirmDeleteExtractionData) {
        confirmDeleteExtractionData.addEventListener('click', function() {
          if (!deleteExtractionDataId) return;

          fetch('/projects/' + projectId + '/extraction-data/' + deleteExtractionDataId, {
              method: 'DELETE',
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
              }
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('deleteExtractionDataModal')).hide();
                extractionDataTable.ajax.reload();
                // Show success toast
                if (window.toastr) {
                  toastr.success(data.message || 'Extraction data deleted successfully');
                }
              } else {
                alert(data.message || 'Error deleting extraction data');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Error deleting extraction data');
            });
        });
      }

      // Initialize table after jQuery and DataTables are loaded
      if (window.jQuery && jQuery.fn.DataTable) {
        initExtractionDataTable();
      } else {
        // Wait for scripts to load
        setTimeout(function() {
          if (window.jQuery && jQuery.fn.DataTable) {
            initExtractionDataTable();
          }
        }, 500);
      }

      // Initialize Extractions DataTable
      let extractionsTable = null;
      const extractionsProjectId = {{ $project->id }};

      function initExtractionsTable() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
          console.error('jQuery or DataTables not available');
          return;
        }

        const $table = jQuery('#extractionsTable');
        if (!$table.length) {
          console.error('Extractions Table not found');
          return;
        }

        extractionsTable = $table.DataTable({
          processing: true,
          serverSide: true,
          responsive: true,
          ajax: {
            url: '/projects/' + extractionsProjectId + '/extractions',
            type: 'GET',
            headers: {
              'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            error: function(xhr, error, thrown) {
              console.error('Extractions DataTables AJAX error:', error, thrown);
            }
          },
          columns: [{
              data: 'nome',
              name: 'nome',
              orderable: true,
              searchable: true
            },
            {
              data: 'tipo',
              name: 'tipo',
              orderable: true,
              searchable: true
            },
            {
              data: 'ai_model',
              name: 'ai_model',
              orderable: false,
              searchable: false
            },
            {
              data: 'template',
              name: 'template',
              orderable: false,
              searchable: false
            },
            {
              data: 'responsavel',
              name: 'responsavel',
              orderable: false,
              searchable: false
            },
            {
              data: 'data',
              name: 'data',
              orderable: true,
              searchable: false
            },
            {
              data: 'progress',
              name: 'progress',
              orderable: false,
              searchable: false
            },
            {
              data: 'id',
              name: 'actions',
              orderable: false,
              searchable: false
            }
          ],
          columnDefs: [{
              targets: 0,
              render: function(data, type, row) {
                const name = jQuery('<div>').text(data || 'Extraction').html();
                if (type !== 'display') {
                  return name;
                }
                const processed = row.processed_studies || 0;
                const total = row.total_studies || 0;
                let icon, color, title;
                if (row.processing_status === 'processing') {
                  icon = 'ri-loader-4-line extraction-status-spin';
                  color = 'text-danger';
                  title = 'Processing (' + (row.pending_jobs || 0) + ' pending jobs)';
                } else if (row.processing_status === 'completed') {
                  icon = 'ri-checkbox-circle-fill';
                  color = 'text-success';
                  title = 'Completed (' + processed + '/' + total + ' studies extracted)';
                } else {
                  icon = 'ri-error-warning-fill';
                  color = 'text-warning';
                  title = 'Stopped with studies left to extract (' + processed + '/' + total + ')';
                }
                return '<i class="icon-base ri ' + icon + ' ' + color + ' me-2" title="' + title + '"></i>' +
                  '<a href="/projects/' + extractionsProjectId + '/extractions/' + row.id +
                  '" class="text-primary fw-semibold" style="text-decoration: none;">' + name + '</a>';
              }
            },
            {
              targets: 6,
              render: function(data, type, row) {
                return '<div class="d-flex align-items-center">' +
                  '<div class="progress flex-grow-1 me-2" style="height: 20px;">' +
                  '<div class="progress-bar" role="progressbar" style="width: ' + data +
                  '%;" aria-valuenow="' + data + '" aria-valuemin="0" aria-valuemax="100">' + data +
                  '%</div>' +
                  '</div>' +
                  '<small class="text-muted">' + (row.processed_studies || 0) + '/' + (row.total_studies ||
                    0) + '</small>' +
                  '</div>';
              }
            },
            {
              targets: 7,
              render: function(data, type, row) {
                let actions = '';
                if (row.tipo === 'Human') {
                  actions +=
                    '<a href="/projects/' + extractionsProjectId + '/extractions/' + data +
                    '" class="btn btn-sm btn-icon btn-label-primary me-1" title="Open">' +
                    '<i class="icon-base ri ri-arrow-right-line"></i></a>';
                } else if (row.tipo === 'AI OpenRouter' || row.tipo === 'AI Local') {
                  actions +=
                    '<button type="button" class="btn btn-sm btn-icon btn-label-primary me-1" onclick="reprocessExtraction(' +
                    data + ')" title="Reprocess">' +
                    '<i class="icon-base ri ri-refresh-line"></i></button>';
                }
                actions +=
                  '<a href="/projects/' + extractionsProjectId + '/extractions/' + data +
                  '/download" class="btn btn-sm btn-icon btn-label-success me-1" title="Download Excel">' +
                  '<i class="icon-base ri ri-download-line"></i></a>';
                actions +=
                  '<button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteExtraction(' +
                  data + ', \'' + (row.nome || '').replace(/'/g, "\\'") + '\')" title="Delete">' +
                  '<i class="icon-base ri ri-delete-bin-line"></i></button>';
                return actions;
              }
            }
          ],
          order: [
            [5, 'desc']
          ],
          pageLength: 10,
          lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
          ],
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
      }

      // Refresh the extractions table every 3 seconds so the status icon and progress stay current
      // (silently: no loading indicator, and the current page is kept)
      setInterval(function() {
        if (!extractionsTable) {
          return;
        }
        const $processing = jQuery('#extractionsTable').closest('.dataTables_wrapper').find('.dataTables_processing');
        $processing.css('display', 'none');
        extractionsTable.ajax.reload(function() {
          $processing.css('display', 'none');
        }, false);
      }, 3000);

      // Initialize extractions table after jQuery and DataTables are loaded
      if (window.jQuery && jQuery.fn.DataTable) {
        initExtractionsTable();
      } else {
        setTimeout(function() {
          if (window.jQuery && jQuery.fn.DataTable) {
            initExtractionsTable();
          }
        }, 500);
      }

      // Get next extraction name (Extraction 1, Extraction 2, etc.)
      function getNextExtractionName() {
        return fetch('/projects/' + extractionsProjectId + '/extractions?draw=1&start=0&length=1', {
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
            return 'Extraction ' + count;
          })
          .catch(error => {
            console.error('Error getting extraction count:', error);
            return 'Extraction 1';
          });
      }

      // Open Add Extraction Modal
      window.openAddExtractionModal = function() {
        document.getElementById('extractionForm').reset();
        document.getElementById('extractionModalTitle').textContent = 'Add Extraction';
        document.getElementById('extraction_id').value = '';

        // Reset selects
        document.getElementById('extraction_tipo').value = '';
        document.getElementById('extraction_ai_model_id').innerHTML = '<option value="">Select AI Model</option>';
        document.getElementById('extraction_template_id').innerHTML = '<option value="">Select Template</option>';
        document.getElementById('extraction_responsavel_id').innerHTML =
          '<option value="">Select Responsible</option>';

        // Hide AI fields initially
        document.getElementById('extraction_ai_fields').style.display = 'none';
        // Remove required from AI fields initially (they will be added when AI type is selected)
        document.getElementById('extraction_ai_model_id').removeAttribute('required');
        document.getElementById('extraction_template_id').removeAttribute('required');

        // Set current date
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('extraction_data').value = today;

        // Get next extraction name
        getNextExtractionName().then(name => {
          const nomeField = document.getElementById('extraction_nome');
          if (nomeField) {
            nomeField.value = name;
          }
        });

        // Load users immediately
        fetch('/projects/' + extractionsProjectId + '/extractions/users', {
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
          })
          .then(response => response.json())
          .then(data => {
            const responsavelSelect = document.getElementById('extraction_responsavel_id');
            responsavelSelect.innerHTML = '<option value="">Select Responsible</option>';
            if (data.success && data.data) {
              data.data.forEach(user => {
                responsavelSelect.innerHTML += '<option value="' + user.id + '">' + user.name + '</option>';
              });
              // Auto-select the project owner by default
              responsavelSelect.value = '{{ $project->user_id }}';
            }
          });

        const modal = new bootstrap.Modal(document.getElementById('extractionModal'));
        modal.show();
      };



      // Handle extraction form submission
      const extractionForm = document.getElementById('extractionForm');
      if (extractionForm) {
        extractionForm.addEventListener('submit', function(e) {
          e.preventDefault();

          const nome = document.getElementById('extraction_nome').value.trim();
          const tipo = document.getElementById('extraction_tipo').value;
          const aiModelId = document.getElementById('extraction_ai_model_id').value;
          const templateId = document.getElementById('extraction_template_id').value;
          const temperature = document.getElementById('extraction_temperature').value;
          const responsavelId = document.getElementById('extraction_responsavel_id').value;
          const data = document.getElementById('extraction_data').value;

          const formData = {
            nome: nome,
            tipo: tipo,
            ai_model_id: aiModelId && aiModelId !== '' ? parseInt(aiModelId) : null,
            template_id: templateId && templateId !== '' ? parseInt(templateId) : null,
            temperature: tipo !== 'Human' && temperature !== '' ? parseFloat(temperature) : null,
            responsavel_id: responsavelId && responsavelId !== '' ? parseInt(responsavelId) : null,
            data: data
          };

          console.log('Submitting extraction form:', formData);

          // Validate required fields
          if (!nome || !tipo || !responsavelId || !data) {
            alert('Please fill in all required fields (Name, Type, Responsible, Date)');
            console.error('Validation failed:', {
              nome,
              tipo,
              responsavelId,
              data
            });
            return;
          }

          // For AI types, validate AI fields
          if (formData.tipo !== 'Human' && (!formData.ai_model_id || !formData.template_id)) {
            alert('AI Model and Template are required for AI extractions');
            return;
          }

          fetch('/projects/' + extractionsProjectId + '/extractions', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
              },
              body: JSON.stringify(formData)
            })
            .then(response => {
              console.log('Response status:', response.status);
              if (!response.ok) {
                return response.json().then(err => {
                  throw new Error(err.message || 'Server error');
                });
              }
              return response.json();
            })
            .then(data => {
              console.log('Response data:', data);
              if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('extractionModal')).hide();

                // If Human type, redirect to the extraction view for manual filling
                if (data.extraction && data.extraction.tipo === 'Human' && data.redirect_url) {
                  window.location.href = data.redirect_url;
                  return;
                }

                // For AI types, reload the table
                if (extractionsTable) {
                  extractionsTable.ajax.reload();
                }
                if (typeof window.showToast === 'function') {
                  window.showToast(data.message || 'Extraction created successfully', 'success');
                } else {
                  alert(data.message || 'Extraction created successfully');
                }
              } else {
                alert(data.message || 'Error creating extraction');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Error creating extraction: ' + error.message);
            });
        });
      }

      // Reprocess extraction
      window.reprocessExtraction = function(extractionId) {
        if (!confirm('Are you sure you want to reprocess this extraction?')) {
          return;
        }
        fetch('/projects/' + extractionsProjectId + '/extractions/' + extractionId + '/process', {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json'
            }
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              if (typeof window.showToast === 'function') {
                window.showToast(data.message || 'Processing started', 'success');
              } else {
                alert(data.message || 'Processing started');
              }
            } else {
              alert(data.message || 'Error starting processing');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Error starting processing');
          });
      };

      // Delete extraction
      window.deleteExtraction = function(extractionId, nome) {
        if (!confirm('Are you sure you want to delete extraction "' + nome + '"? This action cannot be undone.')) {
          return;
        }
        fetch('/projects/' + extractionsProjectId + '/extractions/' + extractionId, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json'
            }
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              if (extractionsTable) {
                extractionsTable.ajax.reload();
              }
              if (typeof window.showToast === 'function') {
                window.showToast(data.message || 'Extraction deleted successfully', 'success');
              } else {
                alert(data.message || 'Extraction deleted successfully');
              }
            } else {
              alert(data.message || 'Error deleting extraction');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Error deleting extraction');
          });
      };
    });

    // Handle tipo change - use event delegation (outside DOMContentLoaded to ensure it's always active)
    document.addEventListener('change', function(e) {
      if (e.target && e.target.id === 'extraction_tipo') {
        const tipo = e.target.value;
        const projectId = {{ $project->id }};
        const aiFields = document.getElementById('extraction_ai_fields');
        const aiModelSelect = document.getElementById('extraction_ai_model_id');
        const templateSelect = document.getElementById('extraction_template_id');

        if (!aiFields || !aiModelSelect || !templateSelect) {
          console.error('Extraction modal elements not found');
          return;
        }

        console.log('Extraction tipo changed to:', tipo);

        if (tipo === 'Human') {
          aiFields.style.display = 'none';
          aiModelSelect.innerHTML = '<option value="">Select AI Model</option>';
          templateSelect.innerHTML = '<option value="">Select Template</option>';
          // Remove required attribute for Human type
          aiModelSelect.removeAttribute('required');
          templateSelect.removeAttribute('required');
        } else if (tipo === 'AI OpenRouter' || tipo === 'AI Local') {
          aiFields.style.display = 'block';
          // Add required attribute for AI types
          aiModelSelect.setAttribute('required', 'required');
          templateSelect.setAttribute('required', 'required');

          // Show loading state
          aiModelSelect.innerHTML = '<option value="">Loading models...</option>';
          templateSelect.innerHTML = '<option value="">Loading templates...</option>';

          // Load AI models
          console.log('Loading AI models for tipo:', tipo);
          fetch('/projects/' + projectId + '/extractions/available-models?tipo=' + encodeURIComponent(tipo), {
              headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
              }
            })
            .then(response => {
              console.log('AI models response status:', response.status);
              if (!response.ok) {
                throw new Error('HTTP ' + response.status);
              }
              return response.json();
            })
            .then(data => {
              console.log('AI models data:', data);
              if (aiModelSelect) {
                aiModelSelect.innerHTML = '<option value="">Select AI Model</option>';
                if (data.success && data.data && Array.isArray(data.data)) {
                  data.data.forEach(model => {
                    aiModelSelect.innerHTML += '<option value="' + model.id + '">' + model.name + '</option>';
                  });
                } else {
                  aiModelSelect.innerHTML = '<option value="">No models available</option>';
                }
              }
            })
            .catch(error => {
              console.error('Error loading AI models:', error);
              if (aiModelSelect) {
                aiModelSelect.innerHTML = '<option value="">Error loading models</option>';
              }
            });

          // Load templates
          console.log('Loading extraction templates');
          fetch('/projects/' + projectId + '/extractions/templates', {
              headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
              }
            })
            .then(response => {
              console.log('Templates response status:', response.status);
              if (!response.ok) {
                throw new Error('HTTP ' + response.status);
              }
              return response.json();
            })
            .then(data => {
              console.log('Templates data:', data);
              if (templateSelect) {
                templateSelect.innerHTML = '<option value="">Select Template</option>';
                if (data.success && data.data && Array.isArray(data.data)) {
                  data.data.forEach(template => {
                    templateSelect.innerHTML += '<option value="' + template.id + '">' + template.name +
                      '</option>';
                  });
                } else {
                  templateSelect.innerHTML = '<option value="">No templates available</option>';
                }
              }
            })
            .catch(error => {
              console.error('Error loading templates:', error);
              if (templateSelect) {
                templateSelect.innerHTML = '<option value="">Error loading templates</option>';
              }
            });
        }
      }
    });
  </script>

  <!-- Extraction Modal -->
  <div class="modal fade" id="extractionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="extractionModalTitle">Add Extraction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="extractionForm">
          <input type="hidden" id="extraction_id" name="id">
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-12">
                <label for="extraction_nome" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" id="extraction_nome" name="nome" class="form-control" required>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-12">
                <label for="extraction_tipo" class="form-label">Type <span class="text-danger">*</span></label>
                <select id="extraction_tipo" name="tipo" class="form-select" required>
                  <option value="">Select Type</option>
                  <option value="Human">Human</option>
                  <option value="AI OpenRouter">AI OpenRouter</option>
                  <option value="AI Local">AI Local</option>
                </select>
              </div>
            </div>
            <div id="extraction_ai_fields" style="display: none;">
              <div class="row mb-3">
                <div class="col-12">
                  <label for="extraction_ai_model_id" class="form-label">AI Model <span
                      class="text-danger">*</span></label>
                  <select id="extraction_ai_model_id" name="ai_model_id" class="form-select" required>
                    <option value="">Select AI Model</option>
                  </select>
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-12">
                  <label for="extraction_template_id" class="form-label">Template <span
                      class="text-danger">*</span></label>
                  <select id="extraction_template_id" name="template_id" class="form-select" required>
                    <option value="">Select Template</option>
                  </select>
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-12">
                  <label for="extraction_temperature" class="form-label">Temperature</label>
                  <input type="number" class="form-control" id="extraction_temperature" name="temperature" min="0"
                    max="2" step="0.01" value="0.1">
                  <div class="form-text">From 0 to 2. Lower values give more consistent answers.</div>
                </div>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-12">
                <label for="extraction_responsavel_id" class="form-label">Responsible <span
                    class="text-danger">*</span></label>
                <select id="extraction_responsavel_id" name="responsavel_id" class="form-select" required>
                  <option value="">Select Responsible</option>
                </select>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-12">
                <label for="extraction_data" class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" id="extraction_data" name="data" class="form-control" required>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection

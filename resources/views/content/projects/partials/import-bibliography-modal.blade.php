{{--
    Import BibTeX/RIS modal, button target #importBibliographyModal.
    Needs $project; uses $searchStrings when the page already loaded them.
--}}
@php
    $bibliographySearchStrings = $searchStrings ?? $project->searchStrings()->orderBy('database')->get();
@endphp
<!-- Import BibTeX/RIS Modal -->
<div class="modal fade" id="importBibliographyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Studies from BibTeX/RIS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importBibliographyForm" enctype="multipart/form-data">
                <div class="modal-body">
                    @if($bibliographySearchStrings->isEmpty())
                    <div class="alert alert-warning mb-0">
                        <i class="icon-base ri ri-error-warning-line me-2"></i>
                        No databases registered yet. Add a search string with its database on the
                        <a href="{{ route('projects.show', $project) }}">project page</a> before importing a BibTeX or RIS file.
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="icon-base ri ri-information-line me-2"></i>
                        Upload the file exported by the database (Scopus, Web of Science, IEEE Xplore, ACM, PubMed...).
                        Title, authors, year, DOI, keywords, abstract, URL, journal/conference and document type are read from each reference,
                        and every imported study is linked to the database you select.
                    </div>
                    <div class="mb-3">
                        <label for="bibliography_search_string_id" class="form-label">Database <span class="text-danger">*</span></label>
                        <select id="bibliography_search_string_id" name="search_string_id" class="form-select" required>
                            <option value="">Select the database the file came from</option>
                            @foreach($bibliographySearchStrings as $searchString)
                            <option value="{{ $searchString->id }}" title="{{ $searchString->string }}">
                                {{ $searchString->database }} — {{ \Illuminate\Support\Str::limit($searchString->string, 60) }}
                            </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Databases registered with the project's search strings.</small>
                    </div>
                    <div class="mb-0">
                        <label for="bibliography_file" class="form-label">Select File <span class="text-danger">*</span></label>
                        <input type="file" id="bibliography_file" name="bibliography_file" class="form-control" accept=".bib,.bibtex,.ris,.txt" required>
                        <small class="text-muted">Max size: 10MB. Supported formats: .bib, .ris (a .txt export is also accepted)</small>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    @if($bibliographySearchStrings->isNotEmpty())
                    <button type="submit" class="btn btn-primary" id="importBibliographySubmitBtn">Import Studies</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Import BibTeX/RIS, linked to the selected search string's database
        const importBibliographyForm = document.getElementById('importBibliographyForm');
        if (importBibliographyForm) {
            importBibliographyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const notify = (message, type) => typeof window.showToast === 'function'
                    ? window.showToast(message, type)
                    : alert(message);
                const searchStringId = document.getElementById('bibliography_search_string_id')?.value;
                const bibliographyFile = document.getElementById('bibliography_file')?.files[0];
                if (!searchStringId) {
                    notify('Please select the database the file came from', 'warning');
                    return;
                }
                if (!bibliographyFile) {
                    notify('Please select a BibTeX or RIS file', 'warning');
                    return;
                }
                const formData = new FormData();
                formData.append('bibliography_file', bibliographyFile);
                formData.append('search_string_id', searchStringId);
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const submitButton = document.getElementById('importBibliographySubmitBtn');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Importing...';
                fetch(@json(route('studies.import-bibliography', $project, false)), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('importBibliographyModal')).hide();
                        importBibliographyForm.reset();
                        if (typeof window.loadStudies === 'function') {
                            window.loadStudies();
                        } else {
                            window.location.reload();
                        }
                        let message = data.message;
                        if (data.errors && data.errors.length > 0) {
                            message += '\nErrors: ' + data.errors.slice(0, 5).join(', ');
                            if (data.errors.length > 5) {
                                message += ' and ' + (data.errors.length - 5) + ' more...';
                            }
                        }
                        notify(message, 'success');
                    } else {
                        notify('Error: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    notify('Error importing file. Please try again.', 'error');
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                });
            });
        }
    });
</script>

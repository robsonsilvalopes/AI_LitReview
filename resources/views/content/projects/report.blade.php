@extends('layouts/contentNavbarLayout')

@section('title', $project->name . ' - Report')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title mb-1">{{ $project->name }}</h2>
                <div class="text-muted mb-3" style="font-size: 0.95rem;">Report</div>
                
                @if($project->description)
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Description</h6>
                    <p class="mb-0">{{ $project->description }}</p>
                </div>
                @endif
                
                <!-- Generate Report Button -->
                <div class="d-flex justify-content-end mb-3">
                    <a href="{{ route('projects.report.generate', $project) }}" class="btn btn-primary" target="_blank">
                        <i class="icon-base ri ri-file-pdf-line me-1"></i>Generate Report
                    </a>
                </div>
                
                <!-- Research Questions Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="researchQuestionsTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Question</th>
                                <th>Answer</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($project->researchQuestions as $question)
                            <tr>
                                <td>{{ $question->custom_id ?? 'N/A' }}</td>
                                <td>{{ $question->research_question }}</td>
                                <td>{{ $question->answer ?? '-' }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="openEditAnswerModal({{ $question->id }}, '{{ addslashes($question->research_question) }}', '{{ addslashes($question->answer ?? '') }}')" title="Edit">
                                        <i class="icon-base ri ri-pencil-line"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No research questions found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Answer Modal -->
<div class="modal fade" id="editAnswerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Answer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editAnswerForm">
                <div class="modal-body">
                    <input type="hidden" id="research_question_id" name="research_question_id">
                    <div class="mb-3">
                        <label for="research_question_text" class="form-label">Research Question</label>
                        <textarea id="research_question_text" class="form-control" rows="3" readonly style="background-color: #f5f6f8; cursor: not-allowed;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="research_question_answer" class="form-label">Answer</label>
                        <textarea id="research_question_answer" name="answer" class="form-control" rows="6" placeholder="Enter your answer here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Answer</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectId = {{ $project->id }};
    
    function openEditAnswerModal(questionId, questionText, currentAnswer) {
        document.getElementById('research_question_id').value = questionId;
        document.getElementById('research_question_text').value = questionText;
        document.getElementById('research_question_answer').value = currentAnswer || '';
        const modal = new bootstrap.Modal(document.getElementById('editAnswerModal'));
        modal.show();
    }
    
    window.openEditAnswerModal = openEditAnswerModal;
    
    const editAnswerForm = document.getElementById('editAnswerForm');
    if (editAnswerForm) {
        editAnswerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const questionId = document.getElementById('research_question_id').value;
            const answer = document.getElementById('research_question_answer').value;
            
            const formData = {
                answer: answer,
                _token: document.querySelector('meta[name="csrf-token"]').content
            };
            
            const submitButton = editAnswerForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
            
            fetch('/projects/' + projectId + '/research-questions/' + questionId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token,
                    'X-HTTP-Method-Override': 'PUT',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    answer: formData.answer
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editAnswerModal'));
                    modal.hide();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving answer. Please try again.');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }
    
    // Reset modal when closed
    const editAnswerModal = document.getElementById('editAnswerModal');
    if (editAnswerModal) {
        editAnswerModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('editAnswerForm').reset();
            document.getElementById('research_question_id').value = '';
        });
    }
});
</script>
@endsection

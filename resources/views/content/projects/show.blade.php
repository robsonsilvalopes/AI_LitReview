@extends('layouts/contentNavbarLayout')

@section('title', $project->name . ' - Project')

@section('content')
<div class="row project-step-anchor" id="project-info">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="position-relative">
                    <h2 class="card-title mb-3" id="project-name">{{ $project->name }}</h2>
                    <button type="button" class="btn btn-sm btn-icon btn-label-primary position-absolute top-0 end-0 edit-project-btn" data-bs-toggle="modal" data-bs-target="#editProjectModal" style="opacity: 0; transition: opacity 0.3s;">
                        <i class="icon-base ri ri-pencil-line"></i>
                    </button>
                </div>
                <!-- Protocol Section -->
                <div class="mt-4">
                    <h5 class="mb-3 text-primary">
                        <i class="icon-base ri ri-file-text-line me-2"></i>Protocol
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-2 small text-uppercase">Description</h6>
                                    <p class="mb-0" id="project-description">{{ $project->description ?? 'Not provided' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-2 small text-uppercase">Objective</h6>
                                    <p class="mb-0" id="project-objetivo">{{ $project->objetivo ?? 'Not provided' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-2 small text-uppercase">Research Time Interval</h6>
                                    <p class="mb-0" id="project-research-time-interval">{{ $project->research_time_interval ?? 'Not provided' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-2 small text-uppercase">Area</h6>
                                    <p class="mb-0" id="project-area">{{ $project->area ?? 'Not provided' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-2 small text-uppercase">Type of Publication</h6>
                                    <p class="mb-0" id="project-type-of-publication">{{ $project->type_of_publication ?? 'Not provided' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                       </div>
                   </div>
               </div>
           </div>
       </div>

       <!-- Statistics Cards -->
       <div class="row mt-4">
          <div class="col-md-4 mb-4">
              <div class="card position-relative" style="overflow: hidden; cursor: pointer;" onclick="window.location.href='{{ route('projects.studies', $project) }}'">
                  <div class="position-absolute top-0 end-0" style="font-size: 150px; font-weight: 700; background: linear-gradient(135deg, #e0e2e8 30%, #a0a2a8 80%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; z-index: 0; user-select: none; transform: translate(-10%, 0%);">1</div>
                  <div class="card-body position-relative" style="z-index: 1;">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                          <div class="d-flex align-items-center gap-2">
                              <div class="avatar">
                                  <div class="avatar-initial bg-primary rounded shadow-xs">
                                      <i class="icon-base ri ri-file-text-line icon-24px"></i>
                                  </div>
                              </div>
                              <h6 class="card-title mb-0">Studies</h6>
                          </div>
                      </div>
                      <div id="chart-studies" style="min-height: 207px;"></div>
                      <div class="mt-3">
                          <div class="d-flex justify-content-between mb-1">
                              <span class="text-muted">Progress</span>
                              <span class="text-muted" id="studies-progress-text">0%</span>
                          </div>
                          <div class="progress" style="height: 8px;">
                              <div class="progress-bar" id="studies-progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                      </div>
                   </div>
               </div>
           </div>
           <div class="col-md-4 mb-4">
               <div class="card position-relative" style="overflow: hidden; cursor: pointer;" onclick="window.location.href='{{ route('projects.duplicates', $project) }}'">
                   <div class="position-absolute top-0 end-0" style="font-size: 150px; font-weight: 700; background: linear-gradient(135deg, #e0e2e8 30%, #a0a2a8 80%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; z-index: 0; user-select: none; transform: translate(-10%, 0%);">2</div>
                   <div class="card-body position-relative" style="z-index: 1;">
                       <div class="d-flex justify-content-between align-items-center mb-3">
                           <div class="d-flex align-items-center gap-2">
                               <div class="avatar">
                                   <div class="avatar-initial bg-warning rounded shadow-xs">
                                       <i class="icon-base ri ri-error-warning-line icon-24px"></i>
                                   </div>
                               </div>
                               <h6 class="card-title mb-0">Duplicates and exclusion criteria</h6>
                           </div>
                       </div>
                       <div id="chart-duplicates" style="min-height: 207px;"></div>
                       <div class="mt-3">
                           <div class="d-flex justify-content-between mb-1">
                               <span class="text-muted">Progress</span>
                               <span class="text-muted" id="duplicates-progress-text">0%</span>
                           </div>
                           <div class="progress" style="height: 8px;">
                               <div class="progress-bar" id="duplicates-progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>
           <div class="col-md-4 mb-4">
               <div class="card position-relative" style="overflow: hidden; cursor: pointer;" onclick="window.location.href='{{ route('projects.initial', $project) }}'">
                   <div class="position-absolute top-0 end-0" style="font-size: 150px; font-weight: 700; background: linear-gradient(135deg, #e0e2e8 30%, #a0a2a8 80%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; z-index: 0; user-select: none; transform: translate(-10%, 0%);">3</div>
                   <div class="card-body position-relative" style="z-index: 1;">
                       <div class="d-flex justify-content-between align-items-center mb-3">
                           <div class="d-flex align-items-center gap-2">
                               <div class="avatar">
                                   <div class="avatar-initial bg-info rounded shadow-xs">
                                       <i class="icon-base ri ri-list-check icon-24px"></i>
                                   </div>
                               </div>
                               <h6 class="card-title mb-0">Initial selection</h6>
                           </div>
                       </div>
                       <div id="chart-initial-selection" style="min-height: 207px;"></div>
                       <div class="mt-3">
                           <div class="d-flex justify-content-between mb-1">
                               <span class="text-muted">Progress</span>
                               <span class="text-muted" id="initial-selection-progress-text">0%</span>
                           </div>
                           <div class="progress" style="height: 8px;">
                               <div class="progress-bar" id="initial-selection-progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>
           <div class="col-md-4 mb-4">
               <div class="card position-relative" style="overflow: hidden; cursor: pointer;" onclick="window.location.href='{{ route('projects.final', $project) }}'">
                   <div class="position-absolute top-0 end-0" style="font-size: 150px; font-weight: 700; background: linear-gradient(135deg, #e0e2e8 30%, #a0a2a8 80%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; z-index: 0; user-select: none; transform: translate(-10%, 0%);">4</div>
                   <div class="card-body position-relative" style="z-index: 1;">
                       <div class="d-flex justify-content-between align-items-center mb-3">
                           <div class="d-flex align-items-center gap-2">
                               <div class="avatar">
                                   <div class="avatar-initial bg-success rounded shadow-xs">
                                       <i class="icon-base ri ri-checkbox-circle-line icon-24px"></i>
                                   </div>
                               </div>
                               <h6 class="card-title mb-0">Final selection</h6>
                           </div>
                       </div>
                       <div id="chart-final-selection" style="min-height: 207px;"></div>
                       <div class="mt-3">
                           <div class="d-flex justify-content-between mb-1">
                               <span class="text-muted">Progress</span>
                               <span class="text-muted" id="final-selection-progress-text">0%</span>
                           </div>
                           <div class="progress" style="height: 8px;">
                               <div class="progress-bar" id="final-selection-progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>
           <div class="col-md-4 mb-4">
               <div class="card position-relative" style="overflow: hidden; cursor: pointer;" onclick="window.location.href='{{ route('projects.extraction', $project) }}'">
                   <div class="position-absolute top-0 end-0" style="font-size: 150px; font-weight: 700; background: linear-gradient(135deg, #e0e2e8 30%, #a0a2a8 80%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; z-index: 0; user-select: none; transform: translate(-10%, 0%);">5</div>
                   <div class="card-body position-relative" style="z-index: 1;">
                       <div class="d-flex justify-content-between align-items-center mb-3">
                           <div class="d-flex align-items-center gap-2">
                               <div class="avatar">
                                   <div class="avatar-initial bg-danger rounded shadow-xs">
                                       <i class="icon-base ri ri-database-2-line icon-24px"></i>
                                   </div>
                               </div>
                               <h6 class="card-title mb-0">Extraction</h6>
                           </div>
                       </div>
                       <div id="chart-extraction" style="min-height: 207px;"></div>
                       <div class="mt-3">
                           <div class="d-flex justify-content-between mb-1">
                               <span class="text-muted">Progress</span>
                               <span class="text-muted" id="extraction-progress-text">0%</span>
                           </div>
                           <div class="progress" style="height: 8px;">
                               <div class="progress-bar" id="extraction-progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>
           <div class="col-md-4 mb-4">
               <div class="card position-relative" style="overflow: hidden; cursor: pointer;" onclick="window.location.href='{{ route('projects.report', $project) }}'">
                   <div class="position-absolute top-0 end-0" style="font-size: 150px; font-weight: 700; background: linear-gradient(135deg, #e0e2e8 30%, #a0a2a8 80%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; z-index: 0; user-select: none; transform: translate(-10%, 0%);">6</div>
                   <div class="card-body position-relative" style="z-index: 1;">
                       <div class="d-flex justify-content-between align-items-center mb-3">
                           <div class="d-flex align-items-center gap-2">
                               <div class="avatar">
                                   <div class="avatar-initial bg-secondary rounded shadow-xs">
                                       <i class="icon-base ri ri-file-chart-line icon-24px"></i>
                                   </div>
                               </div>
                               <h6 class="card-title mb-0">Report</h6>
                           </div>
                       </div>
                       <div id="chart-report" style="min-height: 207px;"></div>
                       <div class="mt-3">
                           <div class="d-flex justify-content-between mb-1">
                               <span class="text-muted">Progress</span>
                               <span class="text-muted" id="report-progress-text">0%</span>
                           </div>
                           <div class="progress" style="height: 8px;">
                               <div class="progress-bar" id="report-progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>
       </div>

       <!-- Sankey Diagram -->
       <div class="row mt-4">
           <div class="col-12">
               <div class="card">
                   <div class="card-header d-flex justify-content-between align-items-center">
                       <h5 class="card-title mb-0">
                           <i class="icon-base ri ri-flow-chart me-2"></i>Study Flow Diagram
                       </h5>
                       <a href="{{ route('projects.prisma', $project) }}" class="btn btn-sm btn-outline-primary">
                           <i class="icon-base ri ri-list-check-3 me-1"></i>PRISMA 2020 flow &amp; checklist
                       </a>
                   </div>
                   <div class="card-body">
                       <div id="sankey-diagram" style="height: 500px;"></div>
                   </div>
               </div>
           </div>
       </div>

       @include('content.projects.partials.question-framework-card')

       <div class="row mt-4 project-step-anchor" id="search-strings">
           <div class="col-12">
               <div class="card">
                   <div class="card-header d-flex justify-content-between align-items-center">
                       <h5 class="card-title mb-0">
                           <i class="icon-base ri ri-search-line me-2"></i>Search Strings
                       </h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSearchStringModal">
                    <i class="icon-base ri ri-add-line me-1"></i>Add Search String
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="searchStringsTable">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Database</th>
                                <th style="width: 50%;">String</th>
                                <th style="width: 25%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($project->searchStrings as $searchString)
                            <tr data-search-string-id="{{ $searchString->id }}">
                                <td>
                                    {{ $searchString->database }}
                                    @if($searchString->api_source)
                                    <br><span class="badge bg-label-info mt-1"><i class="icon-base ri ri-plug-line me-1"></i>{{ \App\Models\SearchString::API_SOURCES[$searchString->api_source] ?? $searchString->api_source }} API</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="icon-base ri ri-search-line me-2 text-primary"></i>
                                        <pre class="mb-0" style="font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; background-color: #f5f5f5; padding: 8px; border-radius: 4px;">{{ $searchString->string }}</pre>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="editSearchString({{ $searchString->id }})" title="Edit">
                                        <i class="icon-base ri ri-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteSearchString({{ $searchString->id }})" title="Delete">
                                        <i class="icon-base ri ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No search strings found. Click "Add Search String" to create one.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4 project-step-anchor" id="research-questions">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-question-line me-2"></i>Research Questions
                </h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createResearchQuestionModal">
                    <i class="icon-base ri ri-add-line me-1"></i>Add Research Question
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="researchQuestionsTable">
                        <thead>
                            <tr>
                                <th style="width: 25%;">ID</th>
                                <th style="width: 50%;">Research Question</th>
                                <th style="width: 25%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($project->researchQuestions as $researchQuestion)
                            <tr data-research-question-id="{{ $researchQuestion->id }}">
                                <td>{{ $researchQuestion->custom_id ?? 'N/A' }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="icon-base ri ri-question-line me-2 text-primary"></i>
                                        <span>{{ $researchQuestion->research_question }}</span>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="editResearchQuestion({{ $researchQuestion->id }})" title="Edit">
                                        <i class="icon-base ri ri-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteResearchQuestion({{ $researchQuestion->id }})" title="Delete">
                                        <i class="icon-base ri ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No research questions found. Click "Add Research Question" to create one.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4 project-step-anchor" id="inclusion-criteria">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-checkbox-circle-line me-2"></i>Inclusion Criteria
                </h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createInclusionCriteriaModal">
                    <i class="icon-base ri ri-add-line me-1"></i>Add Inclusion Criteria
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="inclusionCriteriasTable">
                        <thead>
                            <tr>
                                <th style="width: 25%;">ID</th>
                                <th style="width: 50%;">Inclusion Criteria</th>
                                <th style="width: 25%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($project->inclusionCriterias as $inclusionCriteria)
                            <tr data-inclusion-criteria-id="{{ $inclusionCriteria->id }}">
                                <td>{{ $inclusionCriteria->custom_id ?? 'N/A' }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="icon-base ri ri-checkbox-circle-line me-2 text-success"></i>
                                        <span>{{ $inclusionCriteria->inclusion_criteria }}</span>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="editInclusionCriteria({{ $inclusionCriteria->id }})" title="Edit">
                                        <i class="icon-base ri ri-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteInclusionCriteria({{ $inclusionCriteria->id }})" title="Delete">
                                        <i class="icon-base ri ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No inclusion criteria found. Click "Add Inclusion Criteria" to create one.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4 project-step-anchor" id="exclusion-criteria">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-close-circle-line me-2"></i>Exclusion Criteria
                </h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createExclusionCriteriaModal">
                    <i class="icon-base ri ri-add-line me-1"></i>Add Exclusion Criteria
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="exclusionCriteriasTable">
                        <thead>
                            <tr>
                                <th style="width: 25%;">ID</th>
                                <th style="width: 50%;">Exclusion Criteria</th>
                                <th style="width: 25%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($project->exclusionCriterias as $exclusionCriteria)
                            <tr data-exclusion-criteria-id="{{ $exclusionCriteria->id }}">
                                <td>{{ $exclusionCriteria->custom_id ?? 'N/A' }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="icon-base ri ri-close-circle-line me-2 text-danger"></i>
                                        <span>{{ $exclusionCriteria->exclusion_criteria }}</span>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="editExclusionCriteria({{ $exclusionCriteria->id }})" title="Edit">
                                        <i class="icon-base ri ri-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteExclusionCriteria({{ $exclusionCriteria->id }})" title="Delete">
                                        <i class="icon-base ri ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No exclusion criteria found. Click "Add Exclusion Criteria" to create one.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Collaborators Section -->
@include('content.projects.partials.templates-card')

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-team-line me-2"></i>Collaborators
                </h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCollaboratorModal">
                    <i class="icon-base ri ri-user-add-line me-1"></i>Add Collaborator
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="collaboratorsTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Name</th>
                                <th style="width: 50%;">Email</th>
                                <th style="width: 25%;">Actions</th>
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

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-file-text-line me-2"></i>Studies
                </h5>
                <div>
                    <button type="button" class="btn btn-info btn-sm me-2" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                        <i class="icon-base ri ri-file-excel-2-line me-1"></i>Import Excel/CSV
                    </button>
                    <button type="button" class="btn btn-info btn-sm me-2" data-bs-toggle="modal" data-bs-target="#importBibliographyModal">
                        <i class="icon-base ri ri-book-2-line me-1"></i>Import BibTeX/RIS
                    </button>
                    <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                        <i class="icon-base ri ri-upload-cloud-2-line me-1"></i>Bulk Upload PDFs
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createStudyModal">
                        <i class="icon-base ri ri-add-line me-1"></i>Add Study
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="studiesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>DOI</th>
                                <th>Keywords</th>
                                <th>Source</th>
                                <th>Initial selection</th>
                                <th>Final selection</th>
                                <th>Extraction</th>
                                <th>PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Studies will be loaded via AJAX with pagination -->
                            <tr>
                                <td colspan="11" class="text-center text-muted">
                                    <div class="spinner-border spinner-border-sm me-2" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    Loading studies...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="card-footer">
                    <nav aria-label="Studies pagination" id="studiesPagination">
                        <!-- Pagination will be populated by JavaScript -->
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProjectModalTitle">Edit Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="projectForm">
                <div class="modal-body">
                    <input type="hidden" id="project_id" name="project_id" value="{{ $project->id }}">
                    <div class="row">
                        <div class="col mb-3">
                            <label for="project_name" class="form-label">Project Name <span class="text-danger">*</span></label>
                            <input type="text" id="project_name" name="name" class="form-control" placeholder="Enter project name" value="{{ $project->name }}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="project_description" class="form-label">Description</label>
                            <textarea id="project_description" name="description" class="form-control" rows="4" placeholder="Enter project description">{{ $project->description }}</textarea>
                        </div>
                    </div>
                        <div class="row">
                            <div class="col mb-3">
                                <label for="project_objetivo" class="form-label">Objective</label>
                                <textarea id="project_objetivo" name="objetivo" class="form-control" rows="4" placeholder="Enter project objective">{{ $project->objetivo }}</textarea>
                            </div>
                        </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="project_research_time_interval" class="form-label">Research Time Interval</label>
                            <input type="text" id="project_research_time_interval" name="research_time_interval" class="form-control" placeholder="e.g., 2020-2024" value="{{ $project->research_time_interval }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="project_area" class="form-label">Area</label>
                            <input type="text" id="project_area" name="area" class="form-control" placeholder="Enter research area" value="{{ $project->area }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="project_type_of_publication" class="form-label">Type of Publication</label>
                            <input type="text" id="project_type_of_publication" name="type_of_publication" class="form-control" placeholder="e.g., Journal Article, Conference Paper" value="{{ $project->type_of_publication }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Project Modal -->
<div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteProjectForm">
                <div class="modal-body">
                    <p class="mb-3">This action cannot be undone. This will permanently delete the project.</p>
                    <p class="mb-3">To confirm, please type the project name: <strong id="delete-project-name">{{ $project->name }}</strong></p>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="confirmation_name" class="form-label">Project Name</label>
                            <input type="text" id="confirmation_name" name="confirmation_name" class="form-control" placeholder="Enter project name to confirm" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Project</button>
                </div>
            </form>
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
                            <label class="form-label">Duplicates and exclusion criteria</label>
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
                                        <label for="study_view_duplicados_exclusao" class="form-label">Duplicates and exclusion criteria</label>
                                        <select id="study_view_duplicados_exclusao" name="status_duplicados_exclusao" class="form-select">
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

<!-- Duplicate Details Modal -->

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

@include('content.projects.partials.import-bibliography-modal')

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
                            <li><strong>status_duplicados_exclusao</strong> or <strong>duplicates and exclusion criteria</strong> - Yes/No/1/0/true/false</li>
                            <li><strong>status_selecao_inicial</strong> or <strong>initial selection</strong> - Yes/No/1/0/true/false</li>
                            <li><strong>status_selecao_final</strong> or <strong>final selection</strong> - Yes/No/1/0/true/false</li>
                            <li><strong>status_extracao</strong> or <strong>extraction</strong> - Yes/No/1/0/true/false</li>
                        </ul>
                    </div>
                    @include('content.projects.partials.excel-database-select')
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

<!-- Create/Edit Research Question Modal -->
<div class="modal fade" id="createResearchQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="researchQuestionModalTitle">Create Research Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="researchQuestionForm">
                <div class="modal-body">
                    <input type="hidden" id="research_question_id" name="research_question_id">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="research_question_custom_id" class="form-label">ID</label>
                            <input type="text" id="research_question_custom_id" name="custom_id" class="form-control" placeholder="e.g., RQ1" maxlength="30">
                            <small class="text-muted">Leave blank for auto-generation (RQ1, RQ2...). Max 30 characters.</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="research_question_text" class="form-label">Research Question <span class="text-danger">*</span></label>
                            <textarea id="research_question_text" name="research_question" class="form-control" rows="4" placeholder="Enter research question" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Research Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Research Question Modal -->
<div class="modal fade" id="deleteResearchQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Research Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this research question? This action cannot be undone.</p>
                <p class="mb-0"><strong id="delete-research-question-id"></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteResearchQuestion">Delete Research Question</button>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Search String Modal -->
<div class="modal fade" id="createSearchStringModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="searchStringModalTitle">Create Search String</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="searchStringForm">
                <div class="modal-body">
                    <input type="hidden" id="search_string_id" name="search_string_id">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="search_string_database" class="form-label">Database <span class="text-danger">*</span></label>
                            <input type="text" id="search_string_database" name="database" class="form-control" placeholder="Enter database name" maxlength="250" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="search_string_api_source" class="form-label">API database</label>
                            <select id="search_string_api_source" name="api_source" class="form-select">
                                <option value="">None (import its results from a BibTeX/RIS file)</option>
                                @foreach(\App\Models\SearchString::API_SOURCES as $apiKey => $apiName)
                                <option value="{{ $apiKey }}">{{ $apiName }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Choose the database whose API runs this string from <strong>Studies → Search databases</strong>. Write the string in that database's syntax.</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="search_string_string" class="form-label">String <span class="text-danger">*</span></label>
                            <textarea id="search_string_string" name="string" class="form-control" rows="8" placeholder="Enter search string" style="font-family: 'Courier New', monospace; font-size: 12px;" required></textarea>
                            <small class="text-muted">The string will be displayed in monospace font (code style)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Search String</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create/Edit Inclusion Criteria Modal -->
<div class="modal fade" id="createInclusionCriteriaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inclusionCriteriaModalTitle">Create Inclusion Criteria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="inclusionCriteriaForm">
                <div class="modal-body">
                    <input type="hidden" id="inclusion_criteria_id" name="inclusion_criteria_id">
                    <div class="row">
                        <div class="col mb-3">
                            <label for="inclusion_criteria_custom_id" class="form-label">ID</label>
                            <input type="text" id="inclusion_criteria_custom_id" name="custom_id" class="form-control" placeholder="e.g., IC1" maxlength="30">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="inclusion_criteria_text" class="form-label">Inclusion Criteria <span class="text-danger">*</span></label>
                            <textarea id="inclusion_criteria_text" name="inclusion_criteria" class="form-control" rows="4" placeholder="Enter inclusion criteria" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Inclusion Criteria</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Inclusion Criteria Modal -->
<div class="modal fade" id="deleteInclusionCriteriaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Inclusion Criteria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this inclusion criteria? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteInclusionCriteria">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Exclusion Criteria Modal -->
<div class="modal fade" id="createExclusionCriteriaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exclusionCriteriaModalTitle">Create Exclusion Criteria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="exclusionCriteriaForm">
                <div class="modal-body">
                    <input type="hidden" id="exclusion_criteria_id" name="exclusion_criteria_id">
                    <div class="row">
                        <div class="col mb-3">
                            <label for="exclusion_criteria_custom_id" class="form-label">ID</label>
                            <input type="text" id="exclusion_criteria_custom_id" name="custom_id" class="form-control" placeholder="e.g., EC1" maxlength="30">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="exclusion_criteria_text" class="form-label">Exclusion Criteria <span class="text-danger">*</span></label>
                            <textarea id="exclusion_criteria_text" name="exclusion_criteria" class="form-control" rows="4" placeholder="Enter exclusion criteria" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Exclusion Criteria</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Exclusion Criteria Modal -->
<div class="modal fade" id="deleteExclusionCriteriaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Exclusion Criteria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this exclusion criteria? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteExclusionCriteria">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Search String Modal -->
<div class="modal fade" id="deleteSearchStringModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Search String</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this search string? This action cannot be undone.</p>
                <p class="mb-0"><strong id="delete-search-string-database"></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteSearchString">Delete Search String</button>
            </div>
        </div>
    </div>
</div>

@include('content.projects.partials.project-steps-menu')

<!-- Delete Project Button -->
<div class="row mt-4">
    <div class="col-12">
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteProjectModal">
            <i class="icon-base ri ri-delete-bin-line me-2"></i>Delete Project
        </button>
    </div>
</div>

<!-- Add Collaborator Modal -->
<div class="modal fade" id="addCollaboratorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Collaborator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCollaboratorForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="collaborator_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="collaborator_email" name="email" placeholder="user@example.com" required>
                        <small class="text-muted">Enter the email of a user registered in the system.</small>
                    </div>
                    <div id="addCollaboratorError" class="alert alert-danger" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Collaborator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Collaborator Modal -->
<div class="modal fade" id="deleteCollaboratorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remove Collaborator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this collaborator from the project? This action cannot be undone.</p>
                <p class="mb-0"><strong id="delete-collaborator-name"></strong> (<span id="delete-collaborator-email"></span>)</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteCollaborator">Remove Collaborator</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('vendor-style')
@vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
<!-- DataTables CSS -->
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/responsive.bootstrap5.min.css') }}">
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
<!-- DataTables JS -->
<script src="{{ asset('assets/vendor/libs/datatables/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables/js/responsive.bootstrap5.min.js') }}"></script>
<!-- Plotly.js for Sankey Diagram -->
<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cardBody = document.querySelector('.card-body');
    const editBtn = document.querySelector('.edit-project-btn');
    
    // Show edit button on hover
    if (cardBody && editBtn) {
        cardBody.addEventListener('mouseenter', function() {
            editBtn.style.opacity = '1';
        });
        cardBody.addEventListener('mouseleave', function() {
            editBtn.style.opacity = '0';
        });
    }

    // Handle edit project form
    const editForm = document.getElementById('projectForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const projectId = document.getElementById('project_id').value;
            const formData = {
                name: document.getElementById('project_name').value,
                description: document.getElementById('project_description').value,
                objetivo: document.getElementById('project_objetivo').value,
                research_time_interval: document.getElementById('project_research_time_interval').value,
                area: document.getElementById('project_area').value,
                type_of_publication: document.getElementById('project_type_of_publication').value,
                _token: document.querySelector('meta[name="csrf-token"]').content,
                _method: 'PUT'
            };

            fetch(`/projects/${projectId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token,
                    'X-HTTP-Method-Override': 'PUT'
                },
                body: JSON.stringify({
                    name: formData.name,
                    description: formData.description,
                    objetivo: formData.objetivo,
                    research_time_interval: formData.research_time_interval,
                    area: formData.area,
                    type_of_publication: formData.type_of_publication
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const projectId = document.getElementById('project_id').value;
                    const oldName = document.getElementById('project-name').textContent;
                    const newName = data.project.name;
                    
                    // Update page content
                    document.getElementById('project-name').textContent = newName;
                    document.getElementById('delete-project-name').textContent = newName;
                    
                    // Update Protocol section
                    updateProtocolSection(data.project);
                    
                    // Update menu item if function exists
                    if (window.updateProjectInMenu) {
                        window.updateProjectInMenu(projectId, newName);
                    }
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProjectModal'));
                    modal.hide();
                    
                    // Show success message
                    if (typeof window.showToast === 'function') {
                        window.showToast('Project updated successfully!', 'success');
                    } else {
                        alert('Project updated successfully!');
                    }
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Error updating project: ' + (data.message || 'Unknown error'), 'error');
                    } else {
                        alert('Error updating project: ' + (data.message || 'Unknown error'));
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof window.showToast === 'function') {
                    window.showToast('Error updating project. Please try again.', 'error');
                } else {
                    alert('Error updating project. Please try again.');
                }
            });
        });
    }

    // Handle delete project form
    const deleteForm = document.getElementById('deleteProjectForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const projectId = document.getElementById('project_id').value;
            const confirmationName = document.getElementById('confirmation_name').value;
            const projectName = document.getElementById('delete-project-name').textContent;

            if (confirmationName !== projectName) {
                alert('Project name does not match. Please enter the correct project name.');
                return;
            }

            if (!confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                return;
            }

            fetch(`/projects/${projectId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-HTTP-Method-Override': 'DELETE'
                },
                body: JSON.stringify({
                    confirmation_name: confirmationName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const projectId = document.getElementById('project_id').value;
                    
                    // Remove from menu if function exists
                    if (window.removeProjectFromMenu) {
                        window.removeProjectFromMenu(projectId);
                    }
                    
                    // Redirect to home
                    window.location.href = '/';
                } else {
                    alert('Error deleting project: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting project. Please try again.');
            });
        });
    }

    // Handle create project form
    const createForm = document.getElementById('createProjectForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                name: document.getElementById('create_project_name').value,
                description: document.getElementById('create_project_description').value,
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            fetch('/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token
                },
                body: JSON.stringify({
                    name: formData.name,
                    description: formData.description
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Project created:', data);
                
                if (data.success && data.project && data.project.id) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createProjectModal'));
                    modal.hide();
                    
                    // Reset form
                    createForm.reset();
                    
                    // Small delay to ensure cache is cleared on server
                    setTimeout(() => {
                        // Redirect to the newly created project page
                        window.location.href = '/projects/' + data.project.id;
                    }, 100);
                } else {
                    alert('Error creating project: ' + (data.message || 'Unknown error'));
                    console.error('Error data:', data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating project. Please try again.');
            });
        });
    }

    // Function to update Protocol section
    function updateProtocolSection(project) {
        // Update Description
        const descriptionEl = document.getElementById('project-description');
        if (descriptionEl) {
            descriptionEl.textContent = project.description || 'Not provided';
        }
        
        // Update Objective
        const objetivoEl = document.getElementById('project-objetivo');
        if (objetivoEl) {
            objetivoEl.textContent = project.objetivo || 'Not provided';
        }
        
        // Update Research Time Interval
        const researchTimeEl = document.getElementById('project-research-time-interval');
        if (researchTimeEl) {
            researchTimeEl.textContent = project.research_time_interval || 'Not provided';
        }
        
        // Update Area
        const areaEl = document.getElementById('project-area');
        if (areaEl) {
            areaEl.textContent = project.area || 'Not provided';
        }
        
        // Update Type of Publication
        const typeOfPubEl = document.getElementById('project-type-of-publication');
        if (typeOfPubEl) {
            typeOfPubEl.textContent = project.type_of_publication || 'Not provided';
        }
    }

    // Research Questions management
    const projectId = {{ $project->id }};
    let researchQuestionsData = @json($project->researchQuestions);
    let researchQuestionToDelete = null;

    // Load research questions on page load
    function loadResearchQuestions() {
        fetch(`/projects/${projectId}/research-questions`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            researchQuestionsData = data;
            updateResearchQuestionsTable(data);
        })
        .catch(error => {
            console.error('Error loading research questions:', error);
        });
    }

    // Update research questions table
    function updateResearchQuestionsTable(researchQuestions) {
        const tbody = document.querySelector('#researchQuestionsTable tbody');
        if (!tbody) return;

        if (researchQuestions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No research questions found. Click "Add Research Question" to create one.</td></tr>';
            return;
        }

        tbody.innerHTML = researchQuestions.map(rq => {
            return `
                <tr data-research-question-id="${rq.id}">
                    <td>${rq.custom_id || 'N/A'}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="icon-base ri ri-question-line me-2 text-primary"></i>
                            <span>${rq.research_question}</span>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="editResearchQuestion(${rq.id})" title="Edit">
                            <i class="icon-base ri ri-pencil-line"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteResearchQuestion(${rq.id})" title="Delete">
                            <i class="icon-base ri ri-delete-bin-line"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Edit research question
    window.editResearchQuestion = function(researchQuestionId) {
        const researchQuestion = researchQuestionsData.find(rq => rq.id === researchQuestionId);
        if (!researchQuestion) return;

        document.getElementById('researchQuestionModalTitle').textContent = 'Edit Research Question';
        document.getElementById('research_question_id').value = researchQuestion.id;
        document.getElementById('research_question_custom_id').value = researchQuestion.custom_id || '';
        document.getElementById('research_question_text').value = researchQuestion.research_question;

        const modal = new bootstrap.Modal(document.getElementById('createResearchQuestionModal'));
        modal.show();
    };

    // Delete research question
    window.deleteResearchQuestion = function(researchQuestionId) {
        const researchQuestion = researchQuestionsData.find(rq => rq.id === researchQuestionId);
        if (!researchQuestion) return;

        researchQuestionToDelete = researchQuestion;
        document.getElementById('delete-research-question-id').textContent = researchQuestion.custom_id || 'N/A';
        const modal = new bootstrap.Modal(document.getElementById('deleteResearchQuestionModal'));
        modal.show();
    };

    // Handle research question form submission
    const researchQuestionForm = document.getElementById('researchQuestionForm');
    if (researchQuestionForm) {
        researchQuestionForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                custom_id: document.getElementById('research_question_custom_id').value,
                research_question: document.getElementById('research_question_text').value,
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            const researchQuestionId = document.getElementById('research_question_id').value;
            const isEdit = researchQuestionId !== '';
            const url = isEdit 
                ? `/projects/${projectId}/research-questions/${researchQuestionId}`
                : `/projects/${projectId}/research-questions`;
            const method = isEdit ? 'PUT' : 'POST';

            const submitButton = researchQuestionForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token,
                    'X-HTTP-Method-Override': method,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    custom_id: formData.custom_id,
                    research_question: formData.research_question
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createResearchQuestionModal'));
                    modal.hide();
                    researchQuestionForm.reset();
                    document.getElementById('research_question_id').value = '';
                    document.getElementById('researchQuestionModalTitle').textContent = 'Create Research Question';
                    loadResearchQuestions();
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
                    window.showToast('Error saving research question. Please try again.', 'error');
                } else {
                    alert('Error saving research question. Please try again.');
                }
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }

    // Handle delete research question confirmation
    const confirmDeleteResearchQuestionBtn = document.getElementById('confirmDeleteResearchQuestion');
    if (confirmDeleteResearchQuestionBtn) {
        confirmDeleteResearchQuestionBtn.addEventListener('click', function() {
            if (!researchQuestionToDelete) return;

            fetch(`/projects/${projectId}/research-questions/${researchQuestionToDelete.id}`, {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteResearchQuestionModal'));
                    modal.hide();
                    researchQuestionToDelete = null;
                    loadResearchQuestions();
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
                    window.showToast('Error deleting research question. Please try again.', 'error');
                } else {
                    alert('Error deleting research question. Please try again.');
                }
            });
        });
    }

    // Reset research question modal when closed
    const createResearchQuestionModal = document.getElementById('createResearchQuestionModal');
    if (createResearchQuestionModal) {
        createResearchQuestionModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('researchQuestionForm').reset();
            document.getElementById('research_question_id').value = '';
            document.getElementById('researchQuestionModalTitle').textContent = 'Create Research Question';
        });
    }

    // Set suggested ID when opening create modal
    const createResearchQuestionBtn = document.querySelector('[data-bs-target="#createResearchQuestionModal"]');
    if (createResearchQuestionBtn) {
        createResearchQuestionBtn.addEventListener('click', function() {
            // Calculate next suggested ID from existing data
            let nextId = 'RQ1';
            if (researchQuestionsData && researchQuestionsData.length > 0) {
                // Find the highest RQ number
                let maxNum = 0;
                researchQuestionsData.forEach(rq => {
                    if (rq.custom_id && rq.custom_id.match(/^RQ(\d+)$/)) {
                        const matches = rq.custom_id.match(/^RQ(\d+)$/);
                        const num = parseInt(matches[1]);
                        if (num > maxNum) {
                            maxNum = num;
                        }
                    }
                });
                if (maxNum > 0) {
                    nextId = 'RQ' + (maxNum + 1);
                }
            }
            
            // Set suggested ID after a small delay to ensure modal is open
            setTimeout(() => {
                const customIdInput = document.getElementById('research_question_custom_id');
                if (customIdInput && !customIdInput.value) {
                    customIdInput.value = nextId;
                }
            }, 100);
        });
    }

    // Search Strings management
    let searchStringsData = @json($project->searchStrings);
    let searchStringToDelete = null;

    // Load search strings on page load
    function loadSearchStrings() {
        fetch(`/projects/${projectId}/search-strings`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            searchStringsData = data;
            updateSearchStringsTable(data);
        })
        .catch(error => {
            console.error('Error loading search strings:', error);
        });
    }

    // Update search strings table
    const searchStringApiSources = @json(\App\Models\SearchString::API_SOURCES);
    function escapeSearchStringHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    function updateSearchStringsTable(searchStrings) {
        const tbody = document.querySelector('#searchStringsTable tbody');
        if (!tbody) return;

        if (searchStrings.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No search strings found. Click "Add Search String" to create one.</td></tr>';
            return;
        }

        tbody.innerHTML = searchStrings.map(searchString => {
            return `
                <tr data-search-string-id="${searchString.id}">
                    <td>
                        ${escapeSearchStringHtml(searchString.database)}
                        ${searchString.api_source ? '<br><span class="badge bg-label-info mt-1"><i class="icon-base ri ri-plug-line me-1"></i>' + escapeSearchStringHtml(searchStringApiSources[searchString.api_source] || searchString.api_source) + ' API</span>' : ''}
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="icon-base ri ri-search-line me-2 text-primary"></i>
                            <pre class="mb-0" style="font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; background-color: #f5f5f5; padding: 8px; border-radius: 4px;">${searchString.string}</pre>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="editSearchString(${searchString.id})" title="Edit">
                            <i class="icon-base ri ri-pencil-line"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteSearchString(${searchString.id})" title="Delete">
                            <i class="icon-base ri ri-delete-bin-line"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Edit search string
    window.editSearchString = function(searchStringId) {
        const searchString = searchStringsData.find(s => s.id === searchStringId);
        if (!searchString) return;

        document.getElementById('searchStringModalTitle').textContent = 'Edit Search String';
        document.getElementById('search_string_id').value = searchString.id;
        document.getElementById('search_string_database').value = searchString.database;
        document.getElementById('search_string_api_source').value = searchString.api_source || '';
        document.getElementById('search_string_string').value = searchString.string;

        const modal = new bootstrap.Modal(document.getElementById('createSearchStringModal'));
        modal.show();
    };

    // Delete search string
    window.deleteSearchString = function(searchStringId) {
        const searchString = searchStringsData.find(s => s.id === searchStringId);
        if (!searchString) return;

        searchStringToDelete = searchString;
        document.getElementById('delete-search-string-database').textContent = searchString.database;
        const modal = new bootstrap.Modal(document.getElementById('deleteSearchStringModal'));
        modal.show();
    };

    // Handle search string form submission
    const searchStringForm = document.getElementById('searchStringForm');
    if (searchStringForm) {
        searchStringForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                database: document.getElementById('search_string_database').value,
                api_source: document.getElementById('search_string_api_source').value,
                string: document.getElementById('search_string_string').value,
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            const searchStringId = document.getElementById('search_string_id').value;
            const isEdit = searchStringId !== '';
            const url = isEdit 
                ? `/projects/${projectId}/search-strings/${searchStringId}`
                : `/projects/${projectId}/search-strings`;
            const method = isEdit ? 'PUT' : 'POST';

            const submitButton = searchStringForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token,
                    'X-HTTP-Method-Override': method,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    database: formData.database,
                    api_source: formData.api_source || null,
                    string: formData.string
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createSearchStringModal'));
                    modal.hide();
                    searchStringForm.reset();
                    document.getElementById('search_string_id').value = '';
                    document.getElementById('searchStringModalTitle').textContent = 'Create Search String';
                    loadSearchStrings();
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
                    window.showToast('Error saving search string. Please try again.', 'error');
                } else {
                    alert('Error saving search string. Please try again.');
                }
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }

    // Handle delete search string confirmation
    const confirmDeleteSearchStringBtn = document.getElementById('confirmDeleteSearchString');
    if (confirmDeleteSearchStringBtn) {
        confirmDeleteSearchStringBtn.addEventListener('click', function() {
            if (!searchStringToDelete) return;

            fetch(`/projects/${projectId}/search-strings/${searchStringToDelete.id}`, {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteSearchStringModal'));
                    modal.hide();
                    searchStringToDelete = null;
                    loadSearchStrings();
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
                    window.showToast('Error deleting search string. Please try again.', 'error');
                } else {
                    alert('Error deleting search string. Please try again.');
                }
            });
        });
    }

    // Reset search string modal when closed
    const createSearchStringModal = document.getElementById('createSearchStringModal');
    if (createSearchStringModal) {
        createSearchStringModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('searchStringForm').reset();
            document.getElementById('search_string_id').value = '';
            document.getElementById('searchStringModalTitle').textContent = 'Create Search String';
        });
    }

    // Load Exclusion Criterias
    function loadExclusionCriterias() {
        fetch('/projects/' + projectId + '/exclusion-criterias', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const tbody = document.querySelector('#exclusionCriteriasTable tbody');
            if (!tbody) return;
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No exclusion criteria found. Click "Add Exclusion Criteria" to create one.</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.map(ec => {
                return '<tr data-exclusion-criteria-id="' + ec.id + '">' +
                    '<td>' + (ec.custom_id || 'N/A') + '</td>' +
                    '<td>' +
                        '<div class="d-flex align-items-center">' +
                            '<i class="icon-base ri ri-close-circle-line me-2 text-danger"></i>' +
                            '<span>' + (ec.exclusion_criteria || 'N/A') + '</span>' +
                        '</div>' +
                    '</td>' +
                    '<td>' +
                        '<button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="editExclusionCriteria(' + ec.id + ')" title="Edit">' +
                            '<i class="icon-base ri ri-pencil-line"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteExclusionCriteria(' + ec.id + ')" title="Delete">' +
                            '<i class="icon-base ri ri-delete-bin-line"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>';
            }).join('');
        })
        .catch(error => {
            console.error('Error loading exclusion criterias:', error);
            const tbody = document.querySelector('#exclusionCriteriasTable tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error loading exclusion criterias. Please refresh the page.</td></tr>';
            }
        });
    }

    // Edit Exclusion Criteria
    window.editExclusionCriteria = function(exclusionCriteriaId) {
        fetch('/projects/' + projectId + '/exclusion-criterias', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            const exclusionCriteria = data.find(ec => ec.id === exclusionCriteriaId);
            if (!exclusionCriteria) return;

            document.getElementById('exclusionCriteriaModalTitle').textContent = 'Edit Exclusion Criteria';
            document.getElementById('exclusion_criteria_id').value = exclusionCriteria.id;
            document.getElementById('exclusion_criteria_custom_id').value = exclusionCriteria.custom_id || '';
            document.getElementById('exclusion_criteria_text').value = exclusionCriteria.exclusion_criteria || '';

            const modal = new bootstrap.Modal(document.getElementById('createExclusionCriteriaModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error loading exclusion criteria:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Error loading exclusion criteria', 'error');
            } else {
                alert('Error loading exclusion criteria');
            }
        });
    }

    // Delete Exclusion Criteria
    window.deleteExclusionCriteria = function(exclusionCriteriaId) {
        document.getElementById('confirmDeleteExclusionCriteria').onclick = function() {
            fetch('/projects/' + projectId + '/exclusion-criterias/' + exclusionCriteriaId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-HTTP-Method-Override': 'DELETE',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteExclusionCriteriaModal'));
                    modal.hide();
                    
                    if (typeof window.showToast === 'function') {
                        window.showToast('Exclusion criteria deleted successfully!', 'success');
                    } else {
                        alert('Exclusion criteria deleted successfully!');
                    }
                    
                    loadExclusionCriterias();
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
                    window.showToast('Error deleting exclusion criteria. Please try again.', 'error');
                } else {
                    alert('Error deleting exclusion criteria. Please try again.');
                }
            });
        };

        const modal = new bootstrap.Modal(document.getElementById('deleteExclusionCriteriaModal'));
        modal.show();
    }

    // Exclusion Criteria Form Submit
    const exclusionCriteriaForm = document.getElementById('exclusionCriteriaForm');
    if (exclusionCriteriaForm) {
        exclusionCriteriaForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                custom_id: document.getElementById('exclusion_criteria_custom_id').value,
                exclusion_criteria: document.getElementById('exclusion_criteria_text').value,
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            const exclusionCriteriaId = document.getElementById('exclusion_criteria_id').value;
            const isEdit = exclusionCriteriaId !== '';
            const url = isEdit 
                ? '/projects/' + projectId + '/exclusion-criterias/' + exclusionCriteriaId
                : '/projects/' + projectId + '/exclusion-criterias';
            const method = isEdit ? 'PUT' : 'POST';

            const submitButton = exclusionCriteriaForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token,
                    'X-HTTP-Method-Override': method,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    custom_id: formData.custom_id,
                    exclusion_criteria: formData.exclusion_criteria
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createExclusionCriteriaModal'));
                    modal.hide();
                    exclusionCriteriaForm.reset();
                    document.getElementById('exclusion_criteria_id').value = '';
                    document.getElementById('exclusionCriteriaModalTitle').textContent = 'Create Exclusion Criteria';
                    
                    if (typeof window.showToast === 'function') {
                        window.showToast('Exclusion criteria ' + (isEdit ? 'updated' : 'created') + ' successfully!', 'success');
                    } else {
                        alert('Exclusion criteria ' + (isEdit ? 'updated' : 'created') + ' successfully!');
                    }
                    
                    loadExclusionCriterias();
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
                    window.showToast('Error saving exclusion criteria. Please try again.', 'error');
                } else {
                    alert('Error saving exclusion criteria. Please try again.');
                }
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }

    // Load Inclusion Criterias
    function loadInclusionCriterias() {
        fetch('/projects/' + projectId + '/inclusion-criterias', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const tbody = document.querySelector('#inclusionCriteriasTable tbody');
            if (!tbody) return;
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No inclusion criteria found. Click "Add Inclusion Criteria" to create one.</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.map(ic => {
                return '<tr data-inclusion-criteria-id="' + ic.id + '">' +
                    '<td>' + (ic.custom_id || 'N/A') + '</td>' +
                    '<td>' +
                        '<div class="d-flex align-items-center">' +
                            '<i class="icon-base ri ri-checkbox-circle-line me-2 text-success"></i>' +
                            '<span>' + (ic.inclusion_criteria || 'N/A') + '</span>' +
                        '</div>' +
                    '</td>' +
                    '<td>' +
                        '<button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick="editInclusionCriteria(' + ic.id + ')" title="Edit">' +
                            '<i class="icon-base ri ri-pencil-line"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-sm btn-icon btn-label-danger" onclick="deleteInclusionCriteria(' + ic.id + ')" title="Delete">' +
                            '<i class="icon-base ri ri-delete-bin-line"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>';
            }).join('');
        })
        .catch(error => {
            console.error('Error loading inclusion criterias:', error);
            const tbody = document.querySelector('#inclusionCriteriasTable tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error loading inclusion criterias. Please refresh the page.</td></tr>';
            }
        });
    }

    // Edit Inclusion Criteria
    window.editInclusionCriteria = function(inclusionCriteriaId) {
        fetch('/projects/' + projectId + '/inclusion-criterias', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            const inclusionCriteria = data.find(ic => ic.id === inclusionCriteriaId);
            if (!inclusionCriteria) return;

            document.getElementById('inclusionCriteriaModalTitle').textContent = 'Edit Inclusion Criteria';
            document.getElementById('inclusion_criteria_id').value = inclusionCriteria.id;
            document.getElementById('inclusion_criteria_custom_id').value = inclusionCriteria.custom_id || '';
            document.getElementById('inclusion_criteria_text').value = inclusionCriteria.inclusion_criteria || '';

            const modal = new bootstrap.Modal(document.getElementById('createInclusionCriteriaModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error loading inclusion criteria:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Error loading inclusion criteria', 'error');
            } else {
                alert('Error loading inclusion criteria');
            }
        });
    }

    // Delete Inclusion Criteria
    window.deleteInclusionCriteria = function(inclusionCriteriaId) {
        document.getElementById('confirmDeleteInclusionCriteria').onclick = function() {
            fetch('/projects/' + projectId + '/inclusion-criterias/' + inclusionCriteriaId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-HTTP-Method-Override': 'DELETE',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteInclusionCriteriaModal'));
                    modal.hide();
                    
                    if (typeof window.showToast === 'function') {
                        window.showToast('Inclusion criteria deleted successfully!', 'success');
                    } else {
                        alert('Inclusion criteria deleted successfully!');
                    }
                    
                    loadInclusionCriterias();
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
                    window.showToast('Error deleting inclusion criteria. Please try again.', 'error');
                } else {
                    alert('Error deleting inclusion criteria. Please try again.');
                }
            });
        };

        const modal = new bootstrap.Modal(document.getElementById('deleteInclusionCriteriaModal'));
        modal.show();
    }

    // Inclusion Criteria Form Submit
    const inclusionCriteriaForm = document.getElementById('inclusionCriteriaForm');
    if (inclusionCriteriaForm) {
        inclusionCriteriaForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                custom_id: document.getElementById('inclusion_criteria_custom_id').value,
                inclusion_criteria: document.getElementById('inclusion_criteria_text').value,
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            const inclusionCriteriaId = document.getElementById('inclusion_criteria_id').value;
            const isEdit = inclusionCriteriaId !== '';
            const url = isEdit 
                ? '/projects/' + projectId + '/inclusion-criterias/' + inclusionCriteriaId
                : '/projects/' + projectId + '/inclusion-criterias';
            const method = isEdit ? 'PUT' : 'POST';

            const submitButton = inclusionCriteriaForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token,
                    'X-HTTP-Method-Override': method,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    custom_id: formData.custom_id,
                    inclusion_criteria: formData.inclusion_criteria
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createInclusionCriteriaModal'));
                    modal.hide();
                    inclusionCriteriaForm.reset();
                    document.getElementById('inclusion_criteria_id').value = '';
                    document.getElementById('inclusionCriteriaModalTitle').textContent = 'Create Inclusion Criteria';
                    
                    if (typeof window.showToast === 'function') {
                        window.showToast('Inclusion criteria ' + (isEdit ? 'updated' : 'created') + ' successfully!', 'success');
                    } else {
                        alert('Inclusion criteria ' + (isEdit ? 'updated' : 'created') + ' successfully!');
                    }
                    
                    loadInclusionCriterias();
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
                    window.showToast('Error saving inclusion criteria. Please try again.', 'error');
                } else {
                    alert('Error saving inclusion criteria. Please try again.');
                }
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }

    // Reset Inclusion Criteria Modal when closed
    const createInclusionCriteriaModal = document.getElementById('createInclusionCriteriaModal');
    if (createInclusionCriteriaModal) {
        createInclusionCriteriaModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('inclusionCriteriaForm').reset();
            document.getElementById('inclusion_criteria_id').value = '';
            document.getElementById('inclusionCriteriaModalTitle').textContent = 'Create Inclusion Criteria';
        });
    }

    // Suggest next ID when opening create modal
    const createInclusionCriteriaBtn = document.querySelector('[data-bs-target="#createInclusionCriteriaModal"]');
    if (createInclusionCriteriaBtn) {
        createInclusionCriteriaBtn.addEventListener('click', function() {
            fetch('/projects/' + projectId + '/inclusion-criterias', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                const customIdInput = document.getElementById('inclusion_criteria_custom_id');
                if (customIdInput && !customIdInput.value) {
                    // Find highest IC number
                    let maxNumber = 0;
                    data.forEach(ic => {
                        if (ic.custom_id && ic.custom_id.match(/^IC(\d+)$/)) {
                            const num = parseInt(ic.custom_id.match(/^IC(\d+)$/)[1]);
                            if (num > maxNumber) {
                                maxNumber = num;
                            }
                        }
                    });
                    customIdInput.value = 'IC' + (maxNumber + 1);
                }
            })
            .catch(error => {
                console.error('Error fetching inclusion criterias for ID suggestion:', error);
            });
        });
    }

    // Reset Exclusion Criteria Modal when closed
    const createExclusionCriteriaModal = document.getElementById('createExclusionCriteriaModal');
    if (createExclusionCriteriaModal) {
        createExclusionCriteriaModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('exclusionCriteriaForm').reset();
            document.getElementById('exclusion_criteria_id').value = '';
            document.getElementById('exclusionCriteriaModalTitle').textContent = 'Create Exclusion Criteria';
        });
    }

    // Suggest next ID when opening create modal
    const createExclusionCriteriaBtn = document.querySelector('[data-bs-target="#createExclusionCriteriaModal"]');
    if (createExclusionCriteriaBtn) {
        createExclusionCriteriaBtn.addEventListener('click', function() {
            fetch('/projects/' + projectId + '/exclusion-criterias', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                const customIdInput = document.getElementById('exclusion_criteria_custom_id');
                if (customIdInput && !customIdInput.value) {
                    // Find highest EC number
                    let maxNumber = 0;
                    data.forEach(ec => {
                        if (ec.custom_id && ec.custom_id.match(/^EC(\d+)$/)) {
                            const num = parseInt(ec.custom_id.match(/^EC(\d+)$/)[1]);
                            if (num > maxNumber) {
                                maxNumber = num;
                            }
                        }
                    });
                    customIdInput.value = 'EC' + (maxNumber + 1);
                }
            })
            .catch(error => {
                console.error('Error fetching exclusion criterias for ID suggestion:', error);
            });
        });
    }

    // Studies management
    let studiesData = [];
    let studyToDelete = null;
    let currentPage = 1;
    let studiesPagination = {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0
    };

    // Load studies on page load
    window.loadStudies = function(page = 1) {
        currentPage = page;
        const url = '/projects/' + projectId + '/studies?page=' + page + '&per_page=15';
        console.log('Loading studies from:', url);
        
        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Studies data received:', data);
            studiesData = data.data || data; // Support both paginated and non-paginated responses
            console.log('Studies data array:', studiesData);
            console.log('Is array?', Array.isArray(studiesData));
            studiesPagination = {
                current_page: data.current_page || 1,
                last_page: data.last_page || 1,
                per_page: data.per_page || 15,
                total: data.total || (Array.isArray(studiesData) ? studiesData.length : 0),
                from: data.from || 0,
                to: data.to || 0
            };
            console.log('Pagination info:', studiesPagination);
            console.log('Calling updateStudiesTable with', Array.isArray(studiesData) ? studiesData.length : 0, 'studies');
            updateStudiesTable(studiesData);
            updateStudiesPagination();
        })
        .catch(error => {
            console.error('Error loading studies:', error);
            const tbody = document.querySelector('#studiesTable tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Error loading studies. Please refresh the page.</td></tr>';
            }
        });
    };

    // Update pagination controls
    function updateStudiesPagination() {
        const paginationContainer = document.getElementById('studiesPagination');
        if (!paginationContainer) return;

        if (studiesPagination.total === 0) {
            paginationContainer.innerHTML = '';
            return;
        }

        const current_page = studiesPagination.current_page;
        const last_page = studiesPagination.last_page;
        const from = studiesPagination.from;
        const to = studiesPagination.to;
        const total = studiesPagination.total;
        
        let html = '<ul class="pagination pagination-sm mb-0">';
        
        // First page
        const firstDisabled = current_page === 1 ? 'disabled' : '';
        const firstTabindex = current_page === 1 ? ' tabindex="-1" aria-disabled="true"' : '';
        html += '<li class="page-item ' + firstDisabled + '">' +
            '<a class="page-link" href="javascript:void(0);" onclick="loadStudies(1)"' + firstTabindex + '>' +
                '<i class="icon-base ri ri-skip-back-mini-line"></i>' +
            '</a>' +
        '</li>';
        
        // Previous page
        const prevDisabled = current_page === 1 ? 'disabled' : '';
        const prevTabindex = current_page === 1 ? ' tabindex="-1" aria-disabled="true"' : '';
        html += '<li class="page-item ' + prevDisabled + '">' +
            '<a class="page-link" href="javascript:void(0);" onclick="loadStudies(' + (current_page - 1) + ')"' + prevTabindex + '>' +
                '<i class="icon-base ri ri-arrow-left-s-line"></i>' +
            '</a>' +
        '</li>';
        
        // Page numbers
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(last_page, current_page + 2);
        
        if (startPage > 1) {
            html += '<li class="page-item"><a class="page-link" href="javascript:void(0);" onclick="loadStudies(1)">1</a></li>';
            if (startPage > 2) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === current_page ? 'active' : '';
            html += '<li class="page-item ' + activeClass + '">' +
                '<a class="page-link" href="javascript:void(0);" onclick="loadStudies(' + i + ')">' + i + '</a>' +
            '</li>';
        }
        
        if (endPage < last_page) {
            if (endPage < last_page - 1) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            html += '<li class="page-item"><a class="page-link" href="javascript:void(0);" onclick="loadStudies(' + last_page + ')">' + last_page + '</a></li>';
        }
        
        // Next page
        const nextDisabled = current_page === last_page ? 'disabled' : '';
        const nextTabindex = current_page === last_page ? ' tabindex="-1" aria-disabled="true"' : '';
        html += '<li class="page-item ' + nextDisabled + '">' +
            '<a class="page-link" href="javascript:void(0);" onclick="loadStudies(' + (current_page + 1) + ')"' + nextTabindex + '>' +
                '<i class="icon-base ri ri-arrow-right-s-line"></i>' +
            '</a>' +
        '</li>';
        
        // Last page
        const lastDisabled = current_page === last_page ? 'disabled' : '';
        const lastTabindex = current_page === last_page ? ' tabindex="-1" aria-disabled="true"' : '';
        html += '<li class="page-item ' + lastDisabled + '">' +
            '<a class="page-link" href="javascript:void(0);" onclick="loadStudies(' + last_page + ')"' + lastTabindex + '>' +
                '<i class="icon-base ri ri-skip-forward-mini-line"></i>' +
            '</a>' +
        '</li>';
        
        html += '</ul>';
        
        // Add info text
        html += '<div class="text-muted small mt-2">Showing ' + from + ' to ' + to + ' of ' + total + ' studies</div>';
        
        paginationContainer.innerHTML = html;
    }

    // Update studies table
    function updateStudiesTable(studies) {
        console.log('updateStudiesTable called with', studies);
        const tbody = document.querySelector('#studiesTable tbody');
        if (!tbody) {
            console.error('Table body not found!');
            return;
        }

        if (!studies || studies.length === 0) {
            console.log('No studies to display');
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No studies found. Click "Add Study" to create one.</td></tr>';
            updateStudiesPagination();
            return;
        }

        // Render the current page studies
        renderStudiesTable(studies);
    }

    // Render studies table
    function renderStudiesTable(studies) {
        const tbody = document.querySelector('#studiesTable tbody');
        if (!tbody) return;

        tbody.innerHTML = studies.map((study, index) => {
            
            let statusInitial = '<span class="badge bg-secondary">N/A</span>';
            if (study.status_selecao_inicial === true) {
                statusInitial = '<span class="badge bg-success">Included</span>';
            } else if (study.status_selecao_inicial === false) {
                statusInitial = '<span class="badge bg-danger">Excluded</span>';
            }
            
            let statusFinal = '<span class="badge bg-secondary">N/A</span>';
            if (study.status_selecao_final === true) {
                statusFinal = '<span class="badge bg-success">Included</span>';
            } else if (study.status_selecao_final === false) {
                statusFinal = '<span class="badge bg-danger">Excluded</span>';
            }
            
            let statusExtracao = '<span class="badge bg-secondary">N/A</span>';
            if (study.status_extracao === true) {
                statusExtracao = '<span class="badge bg-success">Included</span>';
            } else if (study.status_extracao === false) {
                statusExtracao = '<span class="badge bg-danger">Excluded</span>';
            }
            
            let pdfLink = '<span class="text-muted">No PDF</span>';
            if (study.pdf_path) {
                pdfLink = '<a href="/storage/s3/' + encodeURIComponent(study.pdf_path) + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="icon-base ri ri-file-pdf-line"></i> View</a>';
            }
            
            let studyTitle = study.name || 'N/A';
            if (study.name && study.name.length > 50) {
                studyTitle = study.name.substring(0, 50) + '...';
            }
            
            let studyDoi = 'N/A';
            if (study.doi) {
                studyDoi = study.doi.length > 30 ? study.doi.substring(0, 30) + '...' : study.doi;
            }
            
            let studyKeywords = 'N/A';
            if (study.keywords) {
                studyKeywords = study.keywords.length > 30 ? study.keywords.substring(0, 30) + '...' : study.keywords;
            }
            
            let studySource = 'N/A';
            if (study.source) {
                studySource = study.source.length > 30 ? study.source.substring(0, 30) + '...' : study.source;
            }
            
            return '<tr data-study-id="' + study.id + '">' +
                '<td><div class="d-flex align-items-center"><span>' + (study.custom_id || 'N/A') + '</span></div></td>' +
                '<td>' +
                    '<div class="d-flex align-items-center">' +
                        '<i class="icon-base ri ri-file-text-line me-2 text-primary"></i>' +
                        '<span>' + studyTitle + '</span>' +
                    '</div>' +
                '</td>' +
                '<td>' + studyDoi + '</td>' +
                '<td>' + studyKeywords + '</td>' +
                '<td>' + studySource + '</td>' +
                '<td>' + statusInitial + '</td>' +
                '<td>' + statusFinal + '</td>' +
                '<td>' + statusExtracao + '</td>' +
                '<td>' + pdfLink + '</td>' +
            '</tr>';
        }).join('');
    }

    // Edit study
    window.editStudy = function(studyId) {
        const study = studiesData.find(s => s.id === studyId);
        if (!study) return;

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
        document.getElementById('study_status_duplicados_exclusao').value = study.status_duplicados_exclusao === null ? '' : (study.status_duplicados_exclusao ? '1' : '0');
        document.getElementById('study_status_selecao_inicial').value = study.status_selecao_inicial === null ? '' : (study.status_selecao_inicial ? '1' : '0');
        document.getElementById('study_status_selecao_final').value = study.status_selecao_final === null ? '' : (study.status_selecao_final ? '1' : '0');
        document.getElementById('study_status_extracao').value = study.status_extracao === null ? '' : (study.status_extracao ? '1' : '0');
        document.getElementById('study_pdf').value = '';

        const modal = new bootstrap.Modal(document.getElementById('createStudyModal'));
        modal.show();
    };

    // Open study view modal
    window.openStudyViewModal = function(studyId) {
        const study = studiesData.find(s => s.id === studyId);
        if (!study) {
            // If not found in current page, fetch it
            fetch('/projects/' + projectId + '/studies?per_page=10000', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
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
                    } else {
                        alert('Study not found');
                    }
                }
            })
            .catch(error => {
                console.error('Error loading study:', error);
                if (typeof window.showToast === 'function') {
                    window.showToast('Error loading study', 'error');
                } else {
                    alert('Error loading study');
                }
            });
            return;
        }
        displayStudyInModal(study);
    };

    // Display study in modal
    function displayStudyInModal(study) {
        // Set study ID
        document.getElementById('study_view_id').value = study.id;
        
        // Set study title
        document.getElementById('study-view-name').textContent = study.name || 'N/A';
        
        // Set abstract (first 50 words)
        const abstractText = (study.abstract || '').trim() || 'No abstract available';
        document.getElementById('study-view-abstract').textContent = abstractText;
        
        // Set selectors
        document.getElementById('study_view_duplicados_exclusao').value = study.status_duplicados_exclusao === null ? '' : (study.status_duplicados_exclusao ? '1' : '0');
        document.getElementById('study_view_selecao_inicial').value = study.status_selecao_inicial === null ? '' : (study.status_selecao_inicial ? '1' : '0');
        document.getElementById('study_view_selecao_final').value = study.status_selecao_final === null ? '' : (study.status_selecao_final ? '1' : '0');
        document.getElementById('study_view_extracao').value = study.status_extracao === null ? '' : (study.status_extracao ? '1' : '0');
        
        // Load PDF preview
        const pdfPreview = document.getElementById('study-pdf-preview');
        if (study.pdf_path) {
            const pdfUrl = '/storage/s3/' + encodeURIComponent(study.pdf_path);
            // Remove padding when PDF is loaded to maximize space
            pdfPreview.style.padding = '0';
            pdfPreview.style.overflow = 'hidden';
            pdfPreview.style.backgroundColor = 'transparent';
            // Use embed with view=FitH to fit page width (works better than iframe for zoom control)
            pdfPreview.style.padding = '0';
            pdfPreview.innerHTML = '<embed src="' + pdfUrl + '#toolbar=0&view=FitH" type="application/pdf" style="width: 100%; height: 100%; border: none; flex: 1; min-height: 0;" />';
        } else {
            pdfPreview.style.padding = '0';
            pdfPreview.style.backgroundColor = '#f5f6f8';
            pdfPreview.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 w-100"><div class="text-center text-muted p-5"><i class="icon-base ri ri-file-pdf-line icon-3x mb-3"></i><p class="mb-0">No PDF available for this study</p></div></div>';
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('studyViewModal'));
        modal.show();
    }

    // Save study view changes
    const saveStudyViewBtn = document.getElementById('saveStudyViewBtn');
    if (saveStudyViewBtn) {
        saveStudyViewBtn.addEventListener('click', function() {
            const studyId = document.getElementById('study_view_id').value;
            if (!studyId) return;

            const formData = {
                status_duplicados_exclusao: document.getElementById('study_view_duplicados_exclusao').value === '' ? null : (document.getElementById('study_view_duplicados_exclusao').value === '1'),
                status_selecao_inicial: document.getElementById('study_view_selecao_inicial').value === '' ? null : (document.getElementById('study_view_selecao_inicial').value === '1'),
                status_selecao_final: document.getElementById('study_view_selecao_final').value === '' ? null : (document.getElementById('study_view_selecao_final').value === '1'),
                status_extracao: document.getElementById('study_view_extracao').value === '' ? null : (document.getElementById('study_view_extracao').value === '1'),
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
                    
                    // Reload studies to reflect changes
                    loadStudies(currentPage);
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


    // Delete study
    window.deleteStudy = function(studyId) {
        const study = studiesData.find(s => s.id === studyId);
        if (!study) return;

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
            formData.append('status_duplicados_exclusao', statusDuplicadosExclusao === '' ? '' : (statusDuplicadosExclusao === '1' ? '1' : '0'));
            
            const statusInitial = document.getElementById('study_status_selecao_inicial').value;
            formData.append('status_selecao_inicial', statusInitial === '' ? '' : (statusInitial === '1' ? '1' : '0'));
            
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

            const url = isEdit 
                ? `/projects/${projectId}/studies/${studyId}`
                : `/projects/${projectId}/studies`;
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
                    loadStudies(1); // Go to first page after creating
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
                           loadStudies(currentPage); // Stay on current page after deleting
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
            const excelSearchStringId = document.getElementById('excel_search_string_id')?.value;
            if (excelSearchStringId) {
                formData.append('search_string_id', excelSearchStringId);
            }
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
                    
                // Reload studies - stay on current page
                loadStudies(currentPage);
                    
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
                    // Show results
                    document.getElementById('uploadResults').style.display = 'block';
                    
                    // Show assigned files
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

                    // Show unassigned files with select dropdowns
                    const unassignedList = document.getElementById('unassignedFilesList');
                    unassignedList.innerHTML = '';
                    if (data.results.unassigned.length > 0) {
                        data.results.unassigned.forEach((item, index) => {
                            const div = document.createElement('div');
                            div.className = 'mb-3 p-3 border rounded';
                            div.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <strong>${item.filename}</strong>
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

                    // Reload studies to show updated PDFs - stay on current page
                    loadStudies(currentPage);

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
                // Remove the unassigned file from the list
                const selectElement = document.getElementById(`study_select_${index}`);
                const container = selectElement.closest('.border');
                container.remove();

                    // Reload studies - go to first page after import
                    loadStudies(1);
                    
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

    // Initial loads
    console.log('Initializing page, projectId:', projectId);
    loadResearchQuestions();
    loadSearchStrings();
    loadInclusionCriterias();
    loadExclusionCriterias();
    console.log('Calling loadStudies(1)...');
    loadStudies(1); // Load first page on initial load
    
    // Initialize statistics charts
    initializeStatisticsCharts();
});

// Statistics Charts
function initializeStatisticsCharts() {
    const statistics = @json($statistics ?? []);
    
    if (!statistics || typeof ApexCharts === 'undefined') {
        console.warn('Statistics data or ApexCharts not available');
        return;
    }
    
    const emptyDonutOptions = () => ({
        series: [1],
        chart: {
            type: 'donut',
            height: 207,
            fontFamily: 'var(--bs-font-family-base)'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '15px',
                            fontWeight: 400,
                            color: 'var(--bs-body-color)',
                            offsetY: 24,
                            formatter: function() {
                                return 'Studies';
                            }
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 500,
                            color: 'var(--bs-heading-color)',
                            offsetY: -10,
                            formatter: function() {
                                return 0;
                            }
                        },
                        total: {
                            show: false
                        }
                    }
                }
            }
        },
        labels: ['Studies'],
        colors: ['rgba(240,242,248,1)'],
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: false
        }
    });

    // Card 1: Studies
    const studiesTotal = statistics.total_studies || 0;
    const manualCount = statistics.studies_by_method?.manual || 0;
    const xlsCsvCount = statistics.studies_by_method?.xls_csv || 0;
    const otherCount = statistics.studies_by_method?.other || 0;
    
    if (studiesTotal === 0) {
        new ApexCharts(document.querySelector('#chart-studies'), emptyDonutOptions()).render();
        new ApexCharts(document.querySelector('#chart-duplicates'), emptyDonutOptions()).render();
        new ApexCharts(document.querySelector('#chart-initial-selection'), emptyDonutOptions()).render();
        new ApexCharts(document.querySelector('#chart-final-selection'), emptyDonutOptions()).render();
        new ApexCharts(document.querySelector('#chart-extraction'), emptyDonutOptions()).render();
        new ApexCharts(document.querySelector('#chart-report'), emptyDonutOptions()).render();

        const zeroProgressIds = [
            ['studies-progress-text', 'studies-progress-bar'],
            ['duplicates-progress-text', 'duplicates-progress-bar'],
            ['initial-selection-progress-text', 'initial-selection-progress-bar'],
            ['final-selection-progress-text', 'final-selection-progress-bar'],
            ['extraction-progress-text', 'extraction-progress-bar'],
            ['report-progress-text', 'report-progress-bar']
        ];

        zeroProgressIds.forEach(([textId, barId]) => {
            const textEl = document.getElementById(textId);
            const barEl = document.getElementById(barId);
            if (textEl) {
                textEl.textContent = '0%';
            }
            if (barEl) {
                barEl.style.width = '0%';
                barEl.setAttribute('aria-valuenow', 0);
            }
        });

        return;
    }

    // Build series array - only include non-zero values
    const studiesSeries = [];
    const studiesLabels = [];
    const studiesColors = [];
    
    if (manualCount > 0) {
        studiesSeries.push(manualCount);
        studiesLabels.push('Manual');
        studiesColors.push('var(--bs-primary)');
    }
    if (xlsCsvCount > 0) {
        studiesSeries.push(xlsCsvCount);
        studiesLabels.push('XLS/CSV');
        studiesColors.push('#9055fdb3');
    }
    if (otherCount > 0) {
        studiesSeries.push(otherCount);
        studiesLabels.push('Other');
        studiesColors.push('#9055fd80');
    }
    
    // If no studies, show a single slice with 0
    if (studiesSeries.length === 0) {
        studiesSeries.push(0);
        studiesLabels.push('Studies');
        studiesColors.push('rgba(240,242,248,1)');
    }
    
    const studiesOptions = {
        series: studiesSeries,
        chart: {
            type: 'donut',
            height: 207,
            fontFamily: 'var(--bs-font-family-base)',
            events: {
                dataPointMouseEnter: function(event, chartContext, config) {
                    const seriesIndex = config.dataPointIndex;
                    const value = studiesSeries[seriesIndex];
                    const label = studiesLabels[seriesIndex];
                    
                    // Update center value
                    setTimeout(function() {
                        const valueElement = document.querySelector('#chart-studies .apexcharts-datalabel-value');
                        if (valueElement) {
                            valueElement.textContent = value;
                        }
                        
                        // Update center label
                        const labelElement = document.querySelector('#chart-studies .apexcharts-datalabel-label');
                        if (labelElement) {
                            labelElement.textContent = label;
                        }
                    }, 10);
                },
                dataPointMouseLeave: function(event, chartContext, config) {
                    // Reset to total
                    setTimeout(function() {
                        const valueElement = document.querySelector('#chart-studies .apexcharts-datalabel-value');
                        if (valueElement) {
                            valueElement.textContent = studiesTotal;
                        }
                        
                        const labelElement = document.querySelector('#chart-studies .apexcharts-datalabel-label');
                        if (labelElement) {
                            labelElement.textContent = 'Studies';
                        }
                    }, 10);
                }
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '15px',
                            fontWeight: 400,
                            color: 'var(--bs-body-color)',
                            offsetY: 24,
                            formatter: function() {
                                return 'Studies';
                            }
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 500,
                            color: 'var(--bs-heading-color)',
                            offsetY: -10,
                            formatter: function() {
                                return studiesTotal;
                            }
                        },
                        total: {
                            show: true,
                            label: 'Studies',
                            fontSize: '15px',
                            fontWeight: 400,
                            color: 'var(--bs-body-color)',
                            offsetY: 24,
                            formatter: function() {
                                return '';
                            }
                        }
                    }
                }
            }
        },
        labels: studiesLabels,
        colors: studiesColors,
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: true,
            fillSeriesColor: false,
            theme: 'light'
        }
    };
    const studiesChart = new ApexCharts(document.querySelector('#chart-studies'), studiesOptions);
    studiesChart.render().then(() => {
        // Force update the center value after render
        setTimeout(function() {
            const valueElement = document.querySelector('#chart-studies .apexcharts-datalabel-value');
            if (valueElement) {
                valueElement.textContent = studiesTotal;
            }
            const labelElement = document.querySelector('#chart-studies .apexcharts-datalabel-label');
            if (labelElement) {
                labelElement.textContent = 'Studies';
            }
        }, 200);
    });
    
    // Progress bar: 0% if 0 studies, 100% if > 0 studies
    const studiesProgress = studiesTotal > 0 ? 100 : 0;
    document.getElementById('studies-progress-text').textContent = studiesProgress + '%';
    document.getElementById('studies-progress-bar').style.width = studiesProgress + '%';
    document.getElementById('studies-progress-bar').setAttribute('aria-valuenow', studiesProgress);
    
    // Card 2: Duplicates and exclusion criteria
    const dupYes = statistics.duplicates_exclusion?.yes || 0;
    const dupNo = statistics.duplicates_exclusion?.no || 0;
    const dupNull = statistics.duplicates_exclusion?.null || 0;
    const dupTotal = dupYes + dupNo + dupNull;
    
    // Progress: 0% if all are null, otherwise use the calculated progress
    const dupProgress = (dupTotal > 0 && dupNull === dupTotal) ? 0 : (statistics.duplicates_exclusion?.progress || 0);
    
    // Build series - always show all three segments, even if 0
    const dupSeries = [dupYes, dupNo, dupNull];
    const dupLabels = ['Included', 'Excluded', 'N/A'];
    const dupColors = ['var(--bs-primary)', '#9055fdb3', 'rgba(240,242,248,1)'];
    
    // If no studies at all, show gray chart with 0
    if (dupTotal === 0) {
        dupSeries[0] = 0;
        dupSeries[1] = 0;
        dupSeries[2] = 0;
        dupColors[0] = 'rgba(240,242,248,1)';
        dupColors[1] = 'rgba(240,242,248,1)';
        dupColors[2] = 'rgba(240,242,248,1)';
    }
    
    const duplicatesOptions = {
        series: dupSeries,
        chart: {
            type: 'donut',
            height: 207,
            fontFamily: 'var(--bs-font-family-base)',
            events: {
                dataPointMouseEnter: function(event, chartContext, config) {
                    const seriesIndex = config.dataPointIndex;
                    const value = dupSeries[seriesIndex];
                    const label = dupLabels[seriesIndex];
                    
                    // Update center value
                    setTimeout(function() {
                        const valueElement = document.querySelector('#chart-duplicates .apexcharts-datalabel-value');
                        if (valueElement) {
                            valueElement.textContent = value;
                        }
                        
                        // Update center label
                        const labelElement = document.querySelector('#chart-duplicates .apexcharts-datalabel-label');
                        if (labelElement) {
                            labelElement.textContent = label;
                        }
                    }, 10);
                },
                dataPointMouseLeave: function(event, chartContext, config) {
                    // Reset to Included count
                    setTimeout(function() {
                        const valueElement = document.querySelector('#chart-duplicates .apexcharts-datalabel-value');
                        if (valueElement) {
                            valueElement.textContent = dupYes;
                        }
                        
                        const labelElement = document.querySelector('#chart-duplicates .apexcharts-datalabel-label');
                        if (labelElement) {
                            labelElement.textContent = 'Studies';
                        }
                    }, 10);
                }
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '15px',
                            fontWeight: 400,
                            color: 'var(--bs-body-color)',
                            offsetY: 24,
                            formatter: function() {
                                return 'Studies';
                            }
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 500,
                            color: 'var(--bs-heading-color)',
                            offsetY: -10,
                            formatter: function() {
                                return dupYes;
                            }
                        },
                        total: {
                            show: false
                        }
                    }
                }
            }
        },
        labels: dupLabels,
        colors: dupColors,
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: true,
            fillSeriesColor: false,
            theme: 'light'
        }
    };
    const duplicatesChart = new ApexCharts(document.querySelector('#chart-duplicates'), duplicatesOptions);
    duplicatesChart.render().then(() => {
        // Force update the center value after render
        setTimeout(function() {
            const valueElement = document.querySelector('#chart-duplicates .apexcharts-datalabel-value');
            if (valueElement) {
                valueElement.textContent = dupYes;
            }
            const labelElement = document.querySelector('#chart-duplicates .apexcharts-datalabel-label');
            if (labelElement) {
                labelElement.textContent = 'Studies';
            }
        }, 200);
    });
    document.getElementById('duplicates-progress-text').textContent = dupProgress + '%';
    document.getElementById('duplicates-progress-bar').style.width = dupProgress + '%';
    document.getElementById('duplicates-progress-bar').setAttribute('aria-valuenow', dupProgress);
    
    // Card 3: Initial selection
    const initYes = statistics.initial_selection?.yes || 0;
    const initNo = statistics.initial_selection?.no || 0;
    const initNull = statistics.initial_selection?.null || 0;
    const initTotal = initYes + initNo + initNull;
    
    // Progress: 0% if all are null, otherwise use the calculated progress
    const initProgress = (initTotal > 0 && initNull === initTotal) ? 0 : (statistics.initial_selection?.progress || 0);
    
    // Build series - always show all three segments, even if 0
    const initSeries = [initYes, initNo, initNull];
    const initLabels = ['Included', 'Excluded', 'N/A'];
    const initColors = ['var(--bs-primary)', '#9055fdb3', 'rgba(240,242,248,1)'];
    
    // If no studies at all, show gray chart with 0
    if (initTotal === 0) {
        initSeries[0] = 0;
        initSeries[1] = 0;
        initSeries[2] = 0;
        initColors[0] = 'rgba(240,242,248,1)';
        initColors[1] = 'rgba(240,242,248,1)';
        initColors[2] = 'rgba(240,242,248,1)';
    }
    
    const initialSelectionOptions = {
        series: initSeries,
        chart: {
            type: 'donut',
            height: 207,
            fontFamily: 'var(--bs-font-family-base)',
            events: {
                dataPointMouseEnter: function(event, chartContext, config) {
                    const seriesIndex = config.dataPointIndex;
                    const value = initSeries[seriesIndex];
                    const label = initLabels[seriesIndex];
                    
                    // Update center value
                    setTimeout(function() {
                        const valueElement = document.querySelector('#chart-initial-selection .apexcharts-datalabel-value');
                        if (valueElement) {
                            valueElement.textContent = value;
                        }
                        
                        // Update center label
                        const labelElement = document.querySelector('#chart-initial-selection .apexcharts-datalabel-label');
                        if (labelElement) {
                            labelElement.textContent = label;
                        }
                    }, 10);
                },
                dataPointMouseLeave: function(event, chartContext, config) {
                    // Reset to Included count
                    setTimeout(function() {
                        const valueElement = document.querySelector('#chart-initial-selection .apexcharts-datalabel-value');
                        if (valueElement) {
                            valueElement.textContent = initYes;
                        }
                        
                        const labelElement = document.querySelector('#chart-initial-selection .apexcharts-datalabel-label');
                        if (labelElement) {
                            labelElement.textContent = 'Studies';
                        }
                    }, 10);
                }
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '15px',
                            fontWeight: 400,
                            color: 'var(--bs-body-color)',
                            offsetY: 24,
                            formatter: function() {
                                return 'Studies';
                            }
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 500,
                            color: 'var(--bs-heading-color)',
                            offsetY: -10,
                            formatter: function() {
                                return initYes;
                            }
                        },
                        total: {
                            show: false
                        }
                    }
                }
            }
        },
        labels: initLabels,
        colors: initColors,
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: true,
            fillSeriesColor: false,
            theme: 'light'
        }
    };
    const initialSelectionChart = new ApexCharts(document.querySelector('#chart-initial-selection'), initialSelectionOptions);
    initialSelectionChart.render().then(() => {
        // Force update the center value after render
        setTimeout(function() {
            const valueElement = document.querySelector('#chart-initial-selection .apexcharts-datalabel-value');
            if (valueElement) {
                valueElement.textContent = initYes;
            }
            const labelElement = document.querySelector('#chart-initial-selection .apexcharts-datalabel-label');
            if (labelElement) {
                labelElement.textContent = 'Studies';
            }
        }, 200);
    });
    document.getElementById('initial-selection-progress-text').textContent = initProgress + '%';
    document.getElementById('initial-selection-progress-bar').style.width = initProgress + '%';
    document.getElementById('initial-selection-progress-bar').setAttribute('aria-valuenow', initProgress);
    
    // Card 4: Final selection
    const finalYes = statistics.final_selection?.yes || 0;
    const finalNo = statistics.final_selection?.no || 0;
    const finalNull = statistics.final_selection?.null || 0;
    const finalTotal = statistics.final_selection?.total || 0; // Total = studies with status_selecao_inicial = 1
    
    // Progress: 0% if all are null, otherwise use the calculated progress
    const finalProgress = (finalTotal > 0 && finalNull === finalTotal) ? 0 : (statistics.final_selection?.progress || 0);
    
    // Build series - always show all three segments, even if 0
    const finalSeries = [finalYes, finalNo, finalNull];
    const finalLabels = ['Included', 'Excluded', 'N/A'];
    const finalColors = ['var(--bs-primary)', '#9055fdb3', 'rgba(240,242,248,1)'];
    
    // If no studies at all, show gray chart with 0
    if (finalTotal === 0) {
        finalSeries[0] = 0;
        finalSeries[1] = 0;
        finalSeries[2] = 0;
        finalColors[0] = 'rgba(240,242,248,1)';
        finalColors[1] = 'rgba(240,242,248,1)';
        finalColors[2] = 'rgba(240,242,248,1)';
    }
    
    const finalSelectionOptions = {
        series: finalSeries,
        chart: {
            type: 'donut',
            height: 207,
            fontFamily: 'var(--bs-font-family-base)',
            events: {
                dataPointMouseEnter: function(event, chartContext, config) {
                    const seriesIndex = config.dataPointIndex;
                    const value = finalSeries[seriesIndex];
                    const label = finalLabels[seriesIndex];
                    
                    // Update center value
                    setTimeout(function() {
                        const valueElement = document.querySelector('#chart-final-selection .apexcharts-datalabel-value');
                        if (valueElement) {
                            valueElement.textContent = value;
                        }
                        
                        // Update center label
                        const labelElement = document.querySelector('#chart-final-selection .apexcharts-datalabel-label');
                        if (labelElement) {
                            labelElement.textContent = label;
                        }
                    }, 10);
                },
                dataPointMouseLeave: function(event, chartContext, config) {
                    // Reset to Included count
                    setTimeout(function() {
                        const valueElement = document.querySelector('#chart-final-selection .apexcharts-datalabel-value');
                        if (valueElement) {
                            valueElement.textContent = finalYes;
                        }
                        
                        const labelElement = document.querySelector('#chart-final-selection .apexcharts-datalabel-label');
                        if (labelElement) {
                            labelElement.textContent = 'Studies';
                        }
                    }, 10);
                }
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '15px',
                            fontWeight: 400,
                            color: 'var(--bs-body-color)',
                            offsetY: 24,
                            formatter: function() {
                                return 'Studies';
                            }
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 500,
                            color: 'var(--bs-heading-color)',
                            offsetY: -10,
                            formatter: function() {
                                return finalYes;
                            }
                        },
                        total: {
                            show: false
                        }
                    }
                }
            }
        },
        labels: finalLabels,
        colors: finalColors,
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: true,
            fillSeriesColor: false,
            theme: 'light'
        }
    };
    const finalSelectionChart = new ApexCharts(document.querySelector('#chart-final-selection'), finalSelectionOptions);
    finalSelectionChart.render().then(() => {
        // Force update the center value after render
        setTimeout(function() {
            const valueElement = document.querySelector('#chart-final-selection .apexcharts-datalabel-value');
            if (valueElement) {
                valueElement.textContent = finalYes;
            }
            const labelElement = document.querySelector('#chart-final-selection .apexcharts-datalabel-label');
            if (labelElement) {
                labelElement.textContent = 'Studies';
            }
        }, 200);
    });
    document.getElementById('final-selection-progress-text').textContent = finalProgress + '%';
    document.getElementById('final-selection-progress-bar').style.width = finalProgress + '%';
    document.getElementById('final-selection-progress-bar').setAttribute('aria-valuenow', finalProgress);
    
    // Card 5: Extraction - Show count of extractions
    const extractionCount = {{ $statistics['extraction']['count'] ?? 0 }};
    const extractionProgress = {{ $statistics['extraction']['progress'] ?? 0 }};
    const extractionColor = extractionCount > 0 ? 'var(--bs-primary)' : 'rgba(240,242,248,1)';
    const extractionEmptyOptions = {
        series: [1],
        chart: {
            type: 'donut',
            height: 207,
            fontFamily: 'var(--bs-font-family-base)'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '15px',
                            fontWeight: 400,
                            color: 'var(--bs-body-color)',
                            offsetY: 24,
                            formatter: function() {
                                return 'Extraction';
                            }
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 500,
                            color: 'var(--bs-heading-color)',
                            offsetY: -10,
                            formatter: function() {
                                return extractionCount;
                            }
                        },
                        total: {
                            show: false
                        }
                    }
                }
            }
        },
        labels: ['Extraction'],
        colors: [extractionColor],
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: false
        }
    };
    const extractionChart = new ApexCharts(document.querySelector('#chart-extraction'), extractionEmptyOptions);
    extractionChart.render();
    document.getElementById('extraction-progress-text').textContent = extractionProgress + '%';
    document.getElementById('extraction-progress-bar').style.width = extractionProgress + '%';
    document.getElementById('extraction-progress-bar').setAttribute('aria-valuenow', extractionProgress);
    
    // Card 6: Report - Show answered research questions
    const reportAnswered = {{ $statistics['report']['answered'] ?? 0 }};
    const reportTotal = {{ $statistics['report']['total'] ?? 0 }};
    const reportProgress = {{ $statistics['report']['progress'] ?? 0 }};
    const reportColor = reportAnswered > 0 ? 'var(--bs-primary)' : 'rgba(240,242,248,1)';
    const reportEmptyOptions = {
        series: [1],
        chart: {
            type: 'donut',
            height: 207,
            fontFamily: 'var(--bs-font-family-base)'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '15px',
                            fontWeight: 400,
                            color: 'var(--bs-body-color)',
                            offsetY: 24,
                            formatter: function() {
                                return 'Report';
                            }
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 500,
                            color: 'var(--bs-heading-color)',
                            offsetY: -10,
                            formatter: function() {
                                return reportAnswered + '/' + reportTotal;
                            }
                        },
                        total: {
                            show: false
                        }
                    }
                }
            }
        },
        labels: ['Report'],
        colors: [reportColor],
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: false
        }
    };
    const reportChart = new ApexCharts(document.querySelector('#chart-report'), reportEmptyOptions);
    reportChart.render();
    document.getElementById('report-progress-text').textContent = reportProgress + '%';
    document.getElementById('report-progress-bar').style.width = reportProgress + '%';
    document.getElementById('report-progress-bar').setAttribute('aria-valuenow', reportProgress);
}

// Collaborators DataTable
let collaboratorsTable = null;
let deleteCollaboratorId = null;

// Script loaders (reuse pattern from initial view)
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
    if (window.jQuery) return Promise.resolve();
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

function initCollaboratorsTable() {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
        console.error('jQuery or DataTables not available');
        return;
    }

    const projectId = {{ $project->id }};
    const $table = jQuery('#collaboratorsTable');
    
    collaboratorsTable = $table.DataTable({
        processing: true,
        serverSide: false,
        responsive: true,
        ajax: {
            url: '/projects/' + projectId + '/collaborators',
            type: 'GET',
            headers: {
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            dataSrc: function(json) {
                if (json.success && json.data) {
                    return json.data;
                }
                return [];
            },
            error: function(xhr, error, thrown) {
                console.error('Error loading collaborators:', error, thrown);
            }
        },
        columns: [
            { data: 'name', name: 'name', orderable: true, searchable: true, width: '25%' },
            { data: 'email', name: 'email', orderable: true, searchable: true, width: '50%' },
            { data: 'id', name: 'actions', orderable: false, searchable: false, width: '25%' }
        ],
        columnDefs: [
            {
                targets: 2,
                render: function(data, type, row) {
                    // Don't show delete button for project owner
                    if (row.is_owner) {
                        return '<span class="badge bg-primary">Owner</span>';
                    }
                    return '<button type="button" class="btn btn-sm btn-label-danger" onclick="openDeleteCollaboratorModal(' + data + ', \'' + row.name.replace(/'/g, "\\'") + '\', \'' + row.email.replace(/'/g, "\\'") + '\')">' +
                           '<i class="icon-base ri ri-delete-bin-line me-1"></i>Remove</button>';
                }
            }
        ],
        order: [[0, 'asc']],
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

function openDeleteCollaboratorModal(userId, name, email) {
    deleteCollaboratorId = userId;
    document.getElementById('delete-collaborator-name').textContent = name;
    document.getElementById('delete-collaborator-email').textContent = email;
    const modal = new bootstrap.Modal(document.getElementById('deleteCollaboratorModal'));
    modal.show();
}

// Add collaborator form submission
document.addEventListener('DOMContentLoaded', function() {
    // Ensure jQuery and DataTables, then init table
    ensureJQuery()
        .then(ensureDataTables)
        .then(initCollaboratorsTable)
        .catch(function(err) { console.error('Failed to load DataTables for collaborators', err); });

    // Add collaborator form
    const addCollaboratorForm = document.getElementById('addCollaboratorForm');
    if (addCollaboratorForm) {
        addCollaboratorForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('collaborator_email').value;
            const projectId = {{ $project->id }};
            const errorDiv = document.getElementById('addCollaboratorError');
            errorDiv.style.display = 'none';
            errorDiv.textContent = '';

            fetch('/projects/' + projectId + '/collaborators', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addCollaboratorModal'));
                    modal.hide();
                    
                    // Reset form
                    addCollaboratorForm.reset();
                    
                    // Reload table
                    if (collaboratorsTable) {
                        collaboratorsTable.ajax.reload();
                    }
                    
                    // Show success message
                    if (typeof showToast === 'function') {
                        showToast('success', data.message || 'Collaborator added successfully');
                    }
                } else {
                    errorDiv.textContent = data.message || 'Error adding collaborator';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorDiv.textContent = 'An error occurred while adding the collaborator';
                errorDiv.style.display = 'block';
            });
        });
    }

    // Delete collaborator confirmation
    const confirmDeleteCollaborator = document.getElementById('confirmDeleteCollaborator');
    if (confirmDeleteCollaborator) {
        confirmDeleteCollaborator.addEventListener('click', function() {
            if (!deleteCollaboratorId) return;

            const projectId = {{ $project->id }};
            
            fetch('/projects/' + projectId + '/collaborators/' + deleteCollaboratorId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteCollaboratorModal'));
                    modal.hide();
                    
                    // Reload table
                    if (collaboratorsTable) {
                        collaboratorsTable.ajax.reload();
                    }
                    
                    // Show success message
                    if (typeof showToast === 'function') {
                        showToast('success', data.message || 'Collaborator removed successfully');
                    }
                } else {
                    alert(data.message || 'Error removing collaborator');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while removing the collaborator');
            });
        });
    }

    // Reset modal on close
    const addCollaboratorModal = document.getElementById('addCollaboratorModal');
    if (addCollaboratorModal) {
        addCollaboratorModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('addCollaboratorForm').reset();
            document.getElementById('addCollaboratorError').style.display = 'none';
        });
    }

    // Sankey Diagram
    const statistics = @json($statistics ?? []);
    if (typeof Plotly !== 'undefined' && statistics && statistics.sankey) {
        const sankeyData = statistics.sankey;
        
        // Define nodes (levels) - estrutura conforme solicitado
        // Ordem: included sempre antes de excluded em cada nível (para ficarem acima)
        // Nó 1: Total Studies
        // Nó 2: Duplicates (included) + Excluded (Duplicates) (excluded, termina no Nó 2)
        // Nó 3: Initial Selection (included) + Excluded (Initial) (excluded, termina no Nó 3)
        // Nó 4: Final Selection (included) + Excluded (Final) (excluded, termina no Nó 4)
        // Nó 5: Extraction (included) + Excluded (Final) (excluded, termina no Nó 5)
        const nodes = [
            { label: 'Total Studies' }, // Nó 1 - índice 0
            { label: 'Duplicates' }, // Nó 2 - included - índice 1
            { label: 'Excluded (Duplicates)' }, // Excluded do Nó 1 - índice 2 (termina no Nó 2)
            { label: 'Initial Selection' }, // Nó 3 - included - índice 3
            { label: 'Excluded (Initial)' }, // Excluded do Nó 2 - índice 4 (termina no Nó 3)
            { label: 'Final Selection' }, // Nó 4 - included - índice 5
            { label: 'Excluded (Final)' }, // Excluded do Nó 3 - índice 6 (termina no Nó 4 e Nó 5)
            { label: 'Extraction' } // Nó 5 - included - índice 7
        ];
        
        // Define links (flows) - estrutura conforme solicitado
        // Included sempre acima, excluded sempre abaixo e param no nó imediatamente seguinte
        // Excluded (Duplicates) termina no Nó 2 (Duplicates) - não continua
        const links = [
            // From Nó 1 (Total Studies)
            { source: 0, target: 1, value: sankeyData.passed_to_initial }, // Included acima → Nó 2 (Duplicates)
            { source: 0, target: 2, value: sankeyData.duplicates_excluded }, // Excluded (Duplicates) abaixo → termina no Nó 2 (índice 2, nó final)
            
            // From Nó 2 (Duplicates) - apenas included continua
            { source: 1, target: 3, value: sankeyData.passed_to_final }, // Included acima → Nó 3 (Initial Selection)
            { source: 1, target: 4, value: sankeyData.initial_excluded }, // Excluded (Initial) abaixo → termina no Nó 3 (índice 4, nó final)
            
            // From Nó 3 (Initial Selection)
            { source: 3, target: 5, value: sankeyData.passed_to_extraction }, // Included acima → Nó 4 (Final Selection)
            { source: 3, target: 6, value: sankeyData.final_excluded }, // Excluded (Final) abaixo → termina no Nó 4 (índice 6, nó final)
            
            // From Nó 4 (Final Selection)
            { source: 5, target: 7, value: sankeyData.passed_to_extraction }, // Included acima → Nó 5 (Extraction)
            { source: 5, target: 6, value: sankeyData.final_excluded } // Excluded (Final) abaixo → termina no Nó 5 (índice 6, mesmo nó)
        ];
        
        // Prepare data for Plotly
        const data = {
            type: 'sankey',
            orientation: 'h', // Horizontal
            node: {
                pad: 20,
                thickness: 20,
                line: {
                    color: 'black',
                    width: 0.5
                },
                label: nodes.map(n => n.label),
                color: [
                    '#6366f1', // Nó 1: Total Studies - indigo
                    '#10b981', // Nó 2: Duplicates (included) - green
                    '#ef4444', // Excluded (Duplicates) - red
                    '#10b981', // Nó 3: Initial Selection (included) - green
                    '#ef4444', // Excluded (Initial) - red
                    '#10b981', // Nó 4: Final Selection (included) - green
                    '#ef4444', // Excluded (Final) - red
                    '#8b5cf6'  // Nó 5: Extraction (included) - purple
                ],
                // Usar arrangement 'snap' para manter ordem e garantir que excluded fiquem abaixo
                // Nós finais (excluded) devem ficar abaixo automaticamente
                arrangement: 'snap'
            },
            link: {
                source: links.map(l => l.source),
                target: links.map(l => l.target),
                value: links.map(l => l.value),
                color: links.map((l) => {
                    // Color based on target node
                    const targetNode = l.target;
                    if (targetNode === 2 || targetNode === 4 || targetNode === 6) {
                        return 'rgba(239, 68, 68, 0.5)'; // Red for excluded
                    } else if (targetNode === 1 || targetNode === 3 || targetNode === 5) {
                        return 'rgba(16, 185, 129, 0.5)'; // Green for passed to next selection
                    } else {
                        return 'rgba(139, 92, 246, 0.5)'; // Purple for extraction
                    }
                })
            }
        };
        
        const layout = {
            title: {
                text: 'Study Flow Through Selection Phases',
                font: {
                    size: 16
                }
            },
            font: {
                size: 12
            },
            paper_bgcolor: 'white',
            plot_bgcolor: 'white',
            margin: {
                l: 50,
                r: 50,
                t: 50,
                b: 50
            }
        };
        
        const config = {
            responsive: true,
            displayModeBar: false
        };
        
        Plotly.newPlot('sankey-diagram', [data], layout, config);
    }
});
</script>
@endsection

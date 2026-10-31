@php
use Illuminate\Support\Str;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->name }} - Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .report-section {
            margin-bottom: 3rem;
            page-break-inside: avoid;
        }
        .report-section h1 {
            font-size: 24px;
            text-align: center;
            margin-bottom: 2rem;
            color: #000;
        }
        .report-section h3 {
            font-size: 18px;
            border-bottom: 2px solid #000;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            margin-top: 2rem;
            color: #000;
        }
        .report-section h4 {
            font-size: 14px;
            color: #333;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        .report-section h5 {
            font-size: 13px;
            color: #555;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            font-size: 11px;
        }
        .report-table th,
        .report-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .report-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .badge-included {
            background-color: #198754;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
        }
        .badge-excluded {
            background-color: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
        }
        .badge-na {
            background-color: #6c757d;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
        }
        p {
            margin-bottom: 0.5rem;
        }
        @media print {
            body {
                padding: 10px;
            }
            .report-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Project Name -->
    <div class="report-section">
        <h1>{{ $project->name }}</h1>
    </div>

    <!-- Protocol -->
    <div class="report-section">
        <h3>Protocol</h3>
        
        @if($project->description)
        <div class="mb-3">
            <h4>Description</h4>
            <p>{{ $project->description }}</p>
        </div>
        @endif

        @if($project->objetivo)
        <div class="mb-3">
            <h4>Objective</h4>
            <p>{{ $project->objetivo }}</p>
        </div>
        @endif

        @if($project->research_time_interval)
        <div class="mb-3">
            <h4>Research Time Interval</h4>
            <p>{{ $project->research_time_interval }}</p>
        </div>
        @endif

        @if($project->area)
        <div class="mb-3">
            <h4>Area</h4>
            <p>{{ $project->area }}</p>
        </div>
        @endif

        @if($project->type_of_publication)
        <div class="mb-3">
            <h4>Type of Publication</h4>
            <p>{{ $project->type_of_publication }}</p>
        </div>
        @endif
    </div>

    <!-- Search Strings -->
    <div class="report-section">
        <h3>Search Strings</h3>
        @if($project->searchStrings->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>Database</th>
                    <th>String</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->searchStrings as $searchString)
                <tr>
                    <td>{{ $searchString->database ?? 'N/A' }}</td>
                    <td>{{ $searchString->string }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No search strings defined.</p>
        @endif
    </div>

    <!-- Inclusion Criteria -->
    <div class="report-section">
        <h3>Inclusion Criteria</h3>
        @if($project->inclusionCriterias->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Criteria</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->inclusionCriterias as $criteria)
                <tr>
                    <td>{{ $criteria->custom_id ?? 'N/A' }}</td>
                    <td>{{ $criteria->inclusion_criteria }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No inclusion criteria defined.</p>
        @endif
    </div>

    <!-- Exclusion Criteria -->
    <div class="report-section">
        <h3>Exclusion Criteria</h3>
        @if($project->exclusionCriterias->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Criteria</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->exclusionCriterias as $criteria)
                <tr>
                    <td>{{ $criteria->custom_id ?? 'N/A' }}</td>
                    <td>{{ $criteria->exclusion_criteria }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No exclusion criteria defined.</p>
        @endif
    </div>

    <!-- Research Questions and Answers -->
    <div class="report-section">
        <h3>Research Questions and Answers</h3>
        @if($project->researchQuestions->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Question</th>
                    <th>Answer</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->researchQuestions as $question)
                <tr>
                    <td>{{ $question->custom_id ?? 'N/A' }}</td>
                    <td>{{ $question->research_question }}</td>
                    <td>{{ $question->answer ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No research questions defined.</p>
        @endif
    </div>

    <!-- Collaborators -->
    <div class="report-section">
        <h3>Collaborators</h3>
        @if($project->collaborators->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->collaborators as $collaborator)
                <tr>
                    <td>{{ $collaborator->name }}</td>
                    <td>{{ $collaborator->email }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No collaborators assigned.</p>
        @endif
    </div>

    <!-- List of Studies (All) -->
    <div class="report-section">
        <h3>List of Studies (All)</h3>
        @if($allStudies->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>DOI</th>
                    <th>Abstract</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allStudies as $study)
                <tr>
                    <td>{{ $study->custom_id ?? 'N/A' }}</td>
                    <td>{{ $study->name ?? 'N/A' }}</td>
                    <td>{{ $study->doi ?? 'N/A' }}</td>
                    <td>{{ Str::limit($study->abstract ?? 'N/A', 200) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No studies found.</p>
        @endif
    </div>

    <!-- Duplicates and Exclusion Criteria -->
    <div class="report-section">
        <h3>List of Studies (Duplicates and Exclusion Criteria)</h3>
        
        <div class="mb-3">
            <strong>Included:</strong> {{ $duplicatesIncluded }} | 
            <strong>Excluded:</strong> {{ $duplicatesExcluded }}
        </div>

        @if($allStudies->where('status_duplicados_exclusao', '!=', null)->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Inclusion Criteria</th>
                    <th>Exclusion Criteria</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allStudies->where('status_duplicados_exclusao', '!=', null) as $study)
                <tr>
                    <td>{{ $study->custom_id ?? 'N/A' }}</td>
                    <td>{{ $study->name ?? 'N/A' }}</td>
                    <td>
                        @if($study->status_duplicados_exclusao)
                            <span class="badge-included">Included</span>
                        @else
                            <span class="badge-excluded">Excluded</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $inclusionCriteria = $study->inclusionCriteriasByType('duplicates')->get();
                        @endphp
                        @if($inclusionCriteria->count() > 0)
                            {{ $inclusionCriteria->pluck('custom_id')->implode(', ') }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @php
                            $exclusionCriteria = $study->exclusionCriteriasByType('duplicates')->get();
                        @endphp
                        @if($exclusionCriteria->count() > 0)
                            {{ $exclusionCriteria->pluck('custom_id')->implode(', ') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No studies evaluated for duplicates and exclusion criteria.</p>
        @endif
    </div>

    <!-- Initial Selection -->
    <div class="report-section">
        <h3>List of Studies (Initial Selection)</h3>
        
        <div class="mb-3">
            <strong>Included:</strong> {{ $initialIncluded }} | 
            <strong>Excluded:</strong> {{ $initialExcluded }}
        </div>

        @if($project->initialScreenings->count() > 0)
        <h4>Screenings</h4>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>AI Model</th>
                    <th>Template</th>
                    <th>Responsible</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->initialScreenings as $screening)
                <tr>
                    <td>{{ $screening->nome }}</td>
                    <td>{{ $screening->tipo }}</td>
                    <td>{{ $screening->aiModel ? $screening->aiModel->name : '-' }}</td>
                    <td>{{ $screening->template ? $screening->template->name : '-' }}</td>
                    <td>{{ $screening->responsavel ? $screening->responsavel->name : '-' }}</td>
                    <td>{{ $screening->data ? $screening->data->format('Y-m-d') : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($allStudies->where('status_selecao_inicial', '!=', null)->count() > 0)
        <h4>Results</h4>
        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Inclusion Criteria</th>
                    <th>Exclusion Criteria</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allStudies->where('status_selecao_inicial', '!=', null) as $study)
                <tr>
                    <td>{{ $study->custom_id ?? 'N/A' }}</td>
                    <td>{{ $study->name ?? 'N/A' }}</td>
                    <td>
                        @if($study->status_selecao_inicial)
                            <span class="badge-included">Included</span>
                        @else
                            <span class="badge-excluded">Excluded</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $inclusionCriteria = $study->inclusionCriteriasByType('initial')->get();
                        @endphp
                        @if($inclusionCriteria->count() > 0)
                            {{ $inclusionCriteria->pluck('custom_id')->implode(', ') }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @php
                            $exclusionCriteria = $study->exclusionCriteriasByType('initial')->get();
                        @endphp
                        @if($exclusionCriteria->count() > 0)
                            {{ $exclusionCriteria->pluck('custom_id')->implode(', ') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No studies evaluated for initial selection.</p>
        @endif
    </div>

    <!-- Final Selection -->
    <div class="report-section">
        <h3>List of Studies (Final Selection)</h3>
        
        <div class="mb-3">
            <strong>Included:</strong> {{ $finalIncluded }} | 
            <strong>Excluded:</strong> {{ $finalExcluded }}
        </div>

        @if($project->finalScreenings->count() > 0)
        <h4>Screenings</h4>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>AI Model</th>
                    <th>Template</th>
                    <th>Responsible</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->finalScreenings as $screening)
                <tr>
                    <td>{{ $screening->nome }}</td>
                    <td>{{ $screening->tipo }}</td>
                    <td>{{ $screening->aiModel ? $screening->aiModel->name : '-' }}</td>
                    <td>{{ $screening->template ? $screening->template->name : '-' }}</td>
                    <td>{{ $screening->responsavel ? $screening->responsavel->name : '-' }}</td>
                    <td>{{ $screening->data ? $screening->data->format('Y-m-d') : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($allStudies->where('status_selecao_final', '!=', null)->count() > 0)
        <h4>Results</h4>
        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Inclusion Criteria</th>
                    <th>Exclusion Criteria</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allStudies->where('status_selecao_final', '!=', null) as $study)
                <tr>
                    <td>{{ $study->custom_id ?? 'N/A' }}</td>
                    <td>{{ $study->name ?? 'N/A' }}</td>
                    <td>
                        @if($study->status_selecao_final)
                            <span class="badge-included">Included</span>
                        @else
                            <span class="badge-excluded">Excluded</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $inclusionCriteria = $study->inclusionCriteriasByType('final')->get();
                        @endphp
                        @if($inclusionCriteria->count() > 0)
                            {{ $inclusionCriteria->pluck('custom_id')->implode(', ') }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @php
                            $exclusionCriteria = $study->exclusionCriteriasByType('final')->get();
                        @endphp
                        @if($exclusionCriteria->count() > 0)
                            {{ $exclusionCriteria->pluck('custom_id')->implode(', ') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No studies evaluated for final selection.</p>
        @endif
    </div>

    <!-- Data Extraction -->
    <div class="report-section">
        <h3>Data Extraction</h3>

        @if($project->extractionData->count() > 0)
        <h4>List of Data to Extract</h4>
        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Value</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->extractionData as $extractionData)
                <tr>
                    <td>{{ $extractionData->custom_id ?? 'N/A' }}</td>
                    <td>{{ $extractionData->name }}</td>
                    <td>{{ $extractionData->value ?? '-' }}</td>
                    <td>{{ $extractionData->note ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($project->extractions->count() > 0)
        <h4>List of Extractions</h4>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>AI Model</th>
                    <th>Template</th>
                    <th>Responsible</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->extractions as $extraction)
                <tr>
                    <td>{{ $extraction->nome }}</td>
                    <td>{{ $extraction->tipo }}</td>
                    <td>{{ $extraction->aiModel ? $extraction->aiModel->name : '-' }}</td>
                    <td>{{ $extraction->template ? $extraction->template->name : '-' }}</td>
                    <td>{{ $extraction->responsavel ? $extraction->responsavel->name : '-' }}</td>
                    <td>{{ $extraction->data ? $extraction->data->format('Y-m-d') : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <h4>List of Studies and Extraction</h4>
        @foreach($project->extractions as $extraction)
        <h5>{{ $extraction->nome }}</h5>
        @php
            $extractionStudies = $allStudies->filter(function($study) use ($extraction) {
                return $study->extractions->contains($extraction->id);
            });
        @endphp
        @if($extractionStudies->count() > 0)
        <table class="report-table">
            <thead>
                <tr>
                    <th>Study ID</th>
                    <th>Study Title</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($extractionStudies as $study)
                @php
                    $extractionPivot = $study->extractions->where('id', $extraction->id)->first();
                @endphp
                <tr>
                    <td>{{ $study->custom_id ?? 'N/A' }}</td>
                    <td>{{ $study->name ?? 'N/A' }}</td>
                    <td>
                        @if($extractionPivot && $extractionPivot->pivot->status !== null)
                            @if($extractionPivot->pivot->status)
                                <span class="badge-included">Included</span>
                            @else
                                <span class="badge-excluded">Excluded</span>
                            @endif
                        @else
                            <span class="badge-na">N/A</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No studies in this extraction.</p>
        @endif
        @endforeach
        @else
        <p>No extractions defined.</p>
        @endif
    </div>
</body>
</html>

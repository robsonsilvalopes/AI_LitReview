<?php

namespace App\Http\Controllers;

use App\Models\Study;
use App\Models\Project;
use App\Models\ExclusionCriteria;
use App\Models\SearchString;
use App\Services\BibliographyParser;
use App\Services\StudyMarkdown;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudyController extends Controller
{
    /**
     * Check if the authenticated user has access to the project (owner or collaborator)
     */
    private function hasAccess(Project $project): bool
    {
        $userId = Auth::id();
        
        // Check if user is the owner
        if ($project->user_id === $userId) {
            return true;
        }
        
        // Check if user is a collaborator
        return $project->collaborators()->where('user_id', $userId)->exists();
    }

    /**
     * Check if the authenticated user is the owner of the project
     */
    private function isOwner(Project $project): bool
    {
        return $project->user_id === Auth::id();
    }

    /**
     * Display a listing of studies for a project.
     */
    public function index(Request $request, Project $project)
    {
        // Ensure user has access to the project (owner or collaborator)
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        // If it's an HTML request (not AJAX), return the view
        // Otherwise return JSON for legacy pagination or DataTables
        $hasDataTablesParams = $request->has('draw') && ($request->has('start') || $request->has('length'));
        $acceptHeader = $request->header('Accept', '');
        $wantsJson = $request->wantsJson()
            || $request->expectsJson()
            || $request->ajax()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || strpos($acceptHeader, 'application/json') !== false;

        if (!$hasDataTablesParams && !$wantsJson) {
            $searchStrings = $project->searchStrings()->orderBy('database')->get();
            $studyDatabases = $project->studyDatabases();

            return view('content.projects.studies', compact('project', 'searchStrings', 'studyDatabases'));
        }

        // DataTables server-side processing parameters
        $draw = (int) $request->get('draw', 1);
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 15);
        
        // Handle search parameter (can be array from DataTables or string)
        $searchParam = $request->get('search', '');
        $searchValue = '';
        if (is_array($searchParam) && isset($searchParam['value'])) {
            $searchValue = $searchParam['value'];
        } elseif (is_string($searchParam)) {
            $searchValue = $searchParam;
        }
        
        // Get sorting info from DataTables
        $orderParam = $request->get('order', []);
        $orderColumnIndex = 0;
        $orderDir = 'asc';
        if (is_array($orderParam) && isset($orderParam[0])) {
            $orderColumnIndex = (int) ($orderParam[0]['column'] ?? 0);
            $orderDir = $orderParam[0]['dir'] ?? 'asc';
        }
        
        // Map DataTables column index to database column
        $columns = ['id', 'custom_id', 'name', 'doi', 'keywords', 'source', 'status_duplicados_exclusao', 'status_selecao_inicial', 'status_selecao_final', 'status_extracao'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';
        
        // Also support legacy parameters for compatibility
        $legacySearch = $request->get('search', '');
        $legacySortBy = $request->get('sort_by', $orderColumn);
        $legacySortDir = $request->get('sort_dir', $orderDir);
        $legacyPerPage = $request->get('per_page', $length);
        
        // Use DataTables params if available, otherwise use legacy
        $search = !empty($searchValue) ? $searchValue : $legacySearch;
        if (is_array($search)) {
            $search = $search['value'] ?? '';
        }
        $search = is_string($search) ? trim($search) : '';
        $sortBy = !empty($orderColumn) ? $orderColumn : $legacySortBy;
        $sortDir = !empty($orderDir) ? $orderDir : $legacySortDir;
        // $length defaults to 15, so check the request itself: legacy calls send page/per_page only
        $usesDataTablesPaging = ($request->has('start') || $request->has('length')) && $length > 0;
        $perPage = $usesDataTablesPaging ? $length : (int) $legacyPerPage;
        $page = $usesDataTablesPaging ? (($start / $length) + 1) : (int) $request->get('page', 1);
        
        // Validate sort direction
        $sortDir = in_array(strtolower($sortDir), ['asc', 'desc']) ? strtolower($sortDir) : 'asc';
        
        // Allowed columns for sorting
        $allowedSortColumns = ['id', 'custom_id', 'name', 'doi', 'keywords', 'source', 'status_duplicados_exclusao', 'status_selecao_inicial', 'status_selecao_final', 'status_extracao', 'year', 'created_at', 'updated_at'];
        $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'id';
        
        $query = $project->studies();
        
        // Filter by duplicates inclusion status (for initial selection view)
        if ($request->has('filter_duplicates_included') && $request->filter_duplicates_included) {
            $query->where('status_duplicados_exclusao', true);
        }
        
        // Filter by initial selection inclusion status (for final selection view)
        if ($request->has('filter_initial_included') && $request->filter_initial_included) {
            $query->where('status_selecao_inicial', true);
        }
        
        // Filter by final selection inclusion status (for extraction view)
        if ($request->has('filter_final_included') && $request->filter_final_included) {
            $query->where('status_selecao_final', true);
        }
        
        // Apply search filter
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('custom_id', 'like', '%' . $search . '%')
                  ->orWhere('abstract', 'like', '%' . $search . '%')
                  ->orWhere('keywords', 'like', '%' . $search . '%');
            });
        }
        
        // Filter by database: the search string a BibTeX/RIS import was linked to, or the
        // free-text source of studies added manually or from Excel. "__none" = no database.
        $databaseFilter = trim((string) $request->get('database', ''));
        if ($databaseFilter === '__none') {
            $query->whereNull('search_string_id')
                ->where(fn ($q) => $q->whereNull('source')->orWhere('source', ''));
        } elseif ($databaseFilter !== '') {
            $searchStringIds = $project->searchStrings()->where('database', $databaseFilter)->pluck('id');
            $query->where(function ($q) use ($searchStringIds, $databaseFilter) {
                $q->whereIn('search_string_id', $searchStringIds)
                    ->orWhere(fn ($q2) => $q2->whereNull('search_string_id')->where('source', $databaseFilter));
            });
        }

        // Filter by the study's own initial selection status (include | exclude | na)
        $initialStatusFilter = $request->get('initial_status');
        if ($initialStatusFilter === 'na') {
            $query->whereNull('status_selecao_inicial');
        } elseif (in_array($initialStatusFilter, ['include', 'exclude'], true)) {
            $query->where('status_selecao_inicial', $initialStatusFilter === 'include');
        }

        // Filter by the study's own final selection status (include | exclude | na)
        $finalStatusFilter = $request->get('final_status');
        if ($finalStatusFilter === 'na') {
            $query->whereNull('status_selecao_final');
        } elseif (in_array($finalStatusFilter, ['include', 'exclude'], true)) {
            $query->where('status_selecao_final', $finalStatusFilter === 'include');
        }

        // Filter by decision status within a specific initial or final screening (include | exclude | na)
        $screeningStatusFilter = $request->get('screening_status');
        $statusScreeningId = (int) $request->get('screening_id');
        $statusPivot = [
            'initial' => ['table' => 'initial_screening_study', 'key' => 'initial_screening_id'],
            'final' => ['table' => 'final_screening_study', 'key' => 'final_screening_id'],
        ][$request->get('criteria_type')] ?? null;
        if (in_array($screeningStatusFilter, ['include', 'exclude', 'na'], true)
            && $statusScreeningId > 0
            && $statusPivot !== null
        ) {
            $pivotTable = $statusPivot['table'];
            $pivotMatch = function ($q) use ($statusScreeningId, $statusPivot, $pivotTable) {
                $q->select(DB::raw(1))
                    ->from($pivotTable)
                    ->whereColumn($pivotTable . '.study_id', 'studies.id')
                    ->where($pivotTable . '.' . $statusPivot['key'], $statusScreeningId);
            };

            if ($screeningStatusFilter === 'na') {
                // No decision yet: no pivot row, or pivot row with null status
                $query->whereNotExists(function ($q) use ($pivotMatch, $pivotTable) {
                    $pivotMatch($q);
                    $q->whereNotNull($pivotTable . '.status');
                });
            } else {
                $query->whereExists(function ($q) use ($pivotMatch, $pivotTable, $screeningStatusFilter) {
                    $pivotMatch($q);
                    $q->where($pivotTable . '.status', $screeningStatusFilter === 'include' ? 1 : 0);
                });
            }
        }

        // Extraction status within a specific extraction (extracted | pending). A study counts as extracted
        // when the AI saved an output for it, or (Human) when its extraction form was saved, which is the
        // same rule the extraction list uses for its progress.
        $statusExtraction = null;
        $extractionId = (int) $request->get('extraction_id');
        if ($extractionId > 0) {
            $statusExtraction = \App\Models\Extraction::where('project_id', $project->id)->find($extractionId);
        }
        $extractedMatch = null;
        if ($statusExtraction) {
            $extractedMatch = function ($q) use ($statusExtraction) {
                if ($statusExtraction->tipo === 'Human') {
                    $q->select(DB::raw(1))
                        ->from('study_extraction_data')
                        ->whereColumn('study_extraction_data.study_id', 'studies.id')
                        ->where('study_extraction_data.extraction_id', $statusExtraction->id);
                } else {
                    $q->select(DB::raw(1))
                        ->from('extraction_study')
                        ->whereColumn('extraction_study.study_id', 'studies.id')
                        ->where('extraction_study.extraction_id', $statusExtraction->id)
                        ->whereNotNull('extraction_study.output_ai');
                }
            };
            $extractionStatusFilter = $request->get('extraction_status');
            if ($extractionStatusFilter === 'extracted') {
                $query->whereExists($extractedMatch);
            } elseif ($extractionStatusFilter === 'pending') {
                $query->whereNotExists($extractedMatch);
            }
        }

        // Get total count (respecting the duplicates filter)
        $baseQuery = $project->studies();
        if ($request->has('filter_duplicates_included') && $request->filter_duplicates_included) {
            $baseQuery->where('status_duplicados_exclusao', true);
        }
        if ($request->has('filter_initial_included') && $request->filter_initial_included) {
            $baseQuery->where('status_selecao_inicial', true);
        }
        if ($request->has('filter_final_included') && $request->filter_final_included) {
            $baseQuery->where('status_selecao_final', true);
        }
        $recordsTotal = $baseQuery->count();
        $recordsFiltered = $query->count();
        
        // Apply sorting and pagination
        $studies = $query->orderBy($sortBy, $sortDir)->paginate($perPage, ['*'], 'page', $page);
        
        // Determine criteria type from request (defaults to 'duplicates')
        $criteriaType = $request->get('criteria_type', 'duplicates');
        $screeningIdParam = $request->get('screening_id');
        
        // Convert screening_id to integer if provided
        $screeningId = null;
        if ($screeningIdParam !== null && $screeningIdParam !== '') {
            $screeningId = (int) $screeningIdParam;
        }
        
        // Cache for screening metadata (to avoid querying multiple times)
        $screeningMetadataCache = [];
        
        // Initialize variables for screening type detection
        $isFinalScreening = false;
        
        // Which studies on this page are already extracted (one query for the page)
        $extractedStudyIds = [];
        if ($extractedMatch) {
            $extractedStudyIds = Study::whereIn('id', collect($studies->items())->pluck('id'))
                ->whereExists($extractedMatch)
                ->pluck('id')
                ->flip()
                ->all();
        }

        // Format data for DataTables
        $formattedData = [];
        foreach ($studies->items() as $study) {
            // Get inclusion criteria for duplicates type
            $inclusionCriteriaDuplicates = $study->inclusionCriteriasByType('duplicates')->get();
            $inclusionCriteriaIdsDuplicates = $inclusionCriteriaDuplicates->pluck('id')->toArray();
            $inclusionCriteriaCustomIdsDuplicates = $inclusionCriteriaDuplicates->pluck('custom_id')->filter()->toArray();
            
            // Get exclusion criteria for duplicates type
            $exclusionCriteriaDuplicates = $study->exclusionCriteriasByType('duplicates')->get();
            $exclusionCriteriaIdsDuplicates = $exclusionCriteriaDuplicates->pluck('id')->toArray();
            $exclusionCriteriaCustomIdsDuplicates = $exclusionCriteriaDuplicates->pluck('custom_id')->filter()->toArray();
            
            // Get inclusion criteria for initial type
            $inclusionCriteriaInitial = $study->inclusionCriteriasByType('initial')->get();
            $inclusionCriteriaIdsInitial = $inclusionCriteriaInitial->pluck('id')->toArray();
            $inclusionCriteriaCustomIdsInitial = $inclusionCriteriaInitial->pluck('custom_id')->filter()->toArray();
            
            // Get exclusion criteria for initial type
            $exclusionCriteriaInitial = $study->exclusionCriteriasByType('initial')->get();
            $exclusionCriteriaIdsInitial = $exclusionCriteriaInitial->pluck('id')->toArray();
            $exclusionCriteriaCustomIdsInitial = $exclusionCriteriaInitial->pluck('custom_id')->filter()->toArray();
            
            // If screening_id is provided, get criteria and status from screening tables
            $isFinalScreening = false;
            if ($screeningId) {
                // Check if it's a final screening first (for final screening pages)
                // Use try-catch to handle case where table doesn't exist yet
                try {
                    $isFinalScreening = \App\Models\FinalScreening::where('id', $screeningId)
                        ->where('project_id', $project->id)
                        ->exists();
                } catch (\Exception $e) {
                    // Table doesn't exist yet, treat as not final screening
                    $isFinalScreening = false;
                }
                
                // If not final, check if it's an initial screening
                if (!$isFinalScreening) {
                    $isInitialScreening = \App\Models\InitialScreening::where('id', $screeningId)
                        ->where('project_id', $project->id)
                        ->exists();
                }
                
                if ($isFinalScreening) {
                    $inclusionCriteriaScreening = $study->inclusionCriteriasByFinalScreening($screeningId)->get();
                    $exclusionCriteriaScreening = $study->exclusionCriteriasByFinalScreening($screeningId)->get();
                } else {
                    // Default to initial screening
                    $inclusionCriteriaScreening = $study->inclusionCriteriasByScreening($screeningId)->get();
                    $exclusionCriteriaScreening = $study->exclusionCriteriasByScreening($screeningId)->get();
                }
                
                $inclusionCriteriaIdsScreening = $inclusionCriteriaScreening->pluck('id')->toArray();
                $exclusionCriteriaIdsScreening = $exclusionCriteriaScreening->pluck('id')->toArray();
                $inclusionCriteriaCustomIdsScreening = $inclusionCriteriaScreening->pluck('custom_id')->filter()->toArray();
                $exclusionCriteriaCustomIdsScreening = $exclusionCriteriaScreening->pluck('custom_id')->filter()->toArray();
                
                // Get status and metadata from appropriate pivot table
                $screeningPivot = null;
                if ($isFinalScreening) {
                    try {
                        $screeningPivot = DB::table('final_screening_study')
                            ->where('study_id', $study->id)
                            ->where('final_screening_id', $screeningId)
                            ->first();
                    } catch (\Exception $e) {
                        // Table doesn't exist yet, keep as null
                        $screeningPivot = null;
                    }
                } else {
                    // Only use initial screening table if it's NOT a final screening
                    $screeningPivot = DB::table('initial_screening_study')
                        ->where('study_id', $study->id)
                        ->where('initial_screening_id', $screeningId)
                        ->first();
                }
                
                // Convert status to boolean or null (handle both integer 0/1 and boolean true/false)
                $inputAi = null;
                $outputAi = null;
                $screeningMetadata = null;
                $statusSelecaoInicial = null;
                $statusSelecaoFinal = null;
                if ($screeningPivot) {
                    $statusValue = $screeningPivot->status;
                    // Convert to boolean explicitly - handle null, integer 0/1, boolean, and string
                    if ($statusValue === null) {
                        $statusValueBool = null;
                    } elseif ($statusValue === 1 || $statusValue === true || $statusValue === '1') {
                        $statusValueBool = true;
                    } elseif ($statusValue === 0 || $statusValue === false || $statusValue === '0') {
                        $statusValueBool = false;
                    } else {
                        $statusValueBool = null;
                    }
                    
                    // Set status based on screening type
                    if ($isFinalScreening) {
                        $statusSelecaoFinal = $statusValueBool;
                    } else {
                        $statusSelecaoInicial = $statusValueBool;
                    }
                    
                    $inputAi = $screeningPivot->input_ai;
                    $outputAi = $screeningPivot->output_ai;
                    // Get metadata from pivot table (per study)
                    $screeningMetadata = [
                        'total_duration' => $screeningPivot->total_duration ?? null,
                        'input_tokens' => $screeningPivot->input_tokens ?? null,
                        'output_tokens' => $screeningPivot->output_tokens ?? null,
                        'total_tokens' => $screeningPivot->total_tokens ?? null,
                        'cost' => $screeningPivot->cost ?? null,
                    ];
                }
                
                // Use screening criteria
                $inclusionCriteriaIds = $inclusionCriteriaIdsScreening;
                $exclusionCriteriaIds = $exclusionCriteriaIdsScreening;
                $inclusionCriteriaCustomIds = $inclusionCriteriaCustomIdsScreening;
                $exclusionCriteriaCustomIds = $exclusionCriteriaCustomIdsScreening;
            } else {
                // Determine which criteria to use based on request type
                if ($criteriaType === 'initial') {
                    $inclusionCriteriaIds = $inclusionCriteriaIdsInitial;
                    $inclusionCriteriaCustomIds = $inclusionCriteriaCustomIdsInitial;
                    $exclusionCriteriaIds = $exclusionCriteriaIdsInitial;
                    $exclusionCriteriaCustomIds = $exclusionCriteriaCustomIdsInitial;
                } elseif ($criteriaType === 'final') {
                    // Get final criteria from study_inclusion_criteria and study_exclusion_criteria with type='final'
                    // These are saved when there's no specific screening_id (from the /final page modal)
                    $inclusionCriteriaFinal = $study->inclusionCriteriasByType('final')->get();
                    $exclusionCriteriaFinal = $study->exclusionCriteriasByType('final')->get();
                    $inclusionCriteriaIds = $inclusionCriteriaFinal->pluck('id')->toArray();
                    $inclusionCriteriaCustomIds = $inclusionCriteriaFinal->pluck('custom_id')->filter()->toArray();
                    $exclusionCriteriaIds = $exclusionCriteriaFinal->pluck('id')->toArray();
                    $exclusionCriteriaCustomIds = $exclusionCriteriaFinal->pluck('custom_id')->filter()->toArray();
                } else {
                    $inclusionCriteriaIds = $inclusionCriteriaIdsDuplicates;
                    $inclusionCriteriaCustomIds = $inclusionCriteriaCustomIdsDuplicates;
                    $exclusionCriteriaIds = $exclusionCriteriaIdsDuplicates;
                    $exclusionCriteriaCustomIds = $exclusionCriteriaCustomIdsDuplicates;
                }
                
                $statusSelecaoInicial = $study->status_selecao_inicial;
                $statusSelecaoFinal = $study->status_selecao_final;
                $screeningMetadata = null;
            }
            
            // Determine if this is for final screening based on criteria_type and screening_id
            $isFinalScreeningContext = ($criteriaType === 'final');
            if ($screeningId) {
                $isFinalScreeningContext = $isFinalScreening ?? false;
            } elseif ($criteriaType === 'final') {
                $isFinalScreeningContext = true;
            }
            
            // Determine final status value - check if status was set from pivot table
            // If screening_id is provided and it's a final screening, use status from pivot if available
            if ($screeningId && $isFinalScreening) {
                // Use status from pivot if it exists (can be true, false, or null)
                if ($screeningPivot !== null) {
                    // Status was set from pivot table, use it (can be true, false, or null)
                    $finalStatusValue = $statusSelecaoFinal; // This will be true, false, or null
                } else {
                    // No pivot found, use null (not evaluated yet for this screening)
                    $finalStatusValue = null;
                }
            } else {
                // No screening_id or not final screening, use study's status
                $finalStatusValue = $study->status_selecao_final === null ? null : (bool) $study->status_selecao_final;
            }
            
            $formattedData[] = [
                'id' => $study->id,
                'custom_id' => $study->custom_id ?? 'N/A',
                'name' => $study->name ?? 'N/A',
                'doi' => $study->doi ?? 'N/A',
                'keywords' => $study->keywords ?? 'N/A',
                'source' => $study->source ?? 'N/A',
                // Needed by the edit form, which otherwise opens these empty and clears them on save
                'year' => $study->year,
                'authors' => $study->authors,
                'document_type' => $study->document_type,
                'journal_conference' => $study->journal_conference,
                'tags' => $study->tags,
                'notes' => $study->notes,
                'status_duplicados_exclusao' => $study->status_duplicados_exclusao,
                'status_selecao_inicial' => ($screeningId && !$isFinalScreening && $screeningPivot !== null) ? ($statusSelecaoInicial === null ? null : (bool) $statusSelecaoInicial) : ($study->status_selecao_inicial === null ? null : (bool) $study->status_selecao_inicial), // Use status from pivot if screening_id provided
                'status_selecao_final' => $finalStatusValue, // Use status from pivot if screening_id provided
                'status_extracao' => $study->status_extracao,
                'comentario_selecao_inicial' => $study->comentario_selecao_inicial,
                'comentario_selecao_final' => $study->comentario_selecao_final,
                'extracted' => $extractedMatch ? isset($extractedStudyIds[$study->id]) : null,
                'pdf_path' => $study->pdf_path,
                'abstract' => $study->abstract,
                'link' => $study->link,
                'input_ai' => $inputAi ?? null, // AI input prompt
                'output_ai' => $outputAi ?? null, // AI output response
                // Duplicates phase criteria
                'inclusion_criteria_duplicates' => $inclusionCriteriaIdsDuplicates,
                'exclusion_criteria_duplicates' => $exclusionCriteriaIdsDuplicates,
                // Initial selection criteria
                'inclusion_criteria_initial' => $inclusionCriteriaIdsInitial,
                'exclusion_criteria_initial' => $exclusionCriteriaIdsInitial,
                // Final selection criteria (for final screening context)
                'inclusion_criteria_final' => $isFinalScreeningContext ? $inclusionCriteriaIds : [],
                'exclusion_criteria_final' => $isFinalScreeningContext ? $exclusionCriteriaIds : [],
                // Generic criteria based on current criteria_type or screening (for checkbox matching)
                'inclusion_criteria_ids' => $inclusionCriteriaIds,
                'exclusion_criteria_ids' => $exclusionCriteriaIds,
                // Custom IDs for display
                'inclusion_criteria_display' => $inclusionCriteriaCustomIds,
                'exclusion_criteria_display' => $exclusionCriteriaCustomIds,
                // Screening metadata (same for all studies in the same screening)
                'screening_metadata' => $screeningMetadata,
            ];
        }
        
        // Format response for DataTables server-side processing
        $response = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $formattedData,
            // Also include pagination info for compatibility
            'current_page' => $studies->currentPage(),
            'last_page' => $studies->lastPage(),
            'per_page' => $studies->perPage(),
            'total' => $studies->total(),
            'from' => $studies->firstItem(),
            'to' => $studies->lastItem(),
        ];
        
        \Log::info('DataTables response', [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data_count' => count($formattedData),
            'first_item' => $formattedData[0] ?? null
        ]);
        
        return response()->json($response);
    }

    public function duplicates(Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        return view('content.projects.duplicates', compact('project'));
    }

    public function initial(Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        return view('content.projects.initial', compact('project'));
    }

    public function final(Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        return view('content.projects.final', compact('project'));
    }

    public function extraction(Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        return view('content.projects.extraction', compact('project'));
    }

    public function report(Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        $project->load('researchQuestions');
        return view('content.projects.report', compact('project'));
    }

    public function generateReport(Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Load all necessary relationships
        $project->load([
            'researchQuestions',
            'searchStrings',
            'inclusionCriterias',
            'exclusionCriterias',
            'collaborators',
            'studies',
            'initialScreenings' => function($query) {
                $query->with(['aiModel', 'template', 'responsavel']);
            },
            'finalScreenings' => function($query) {
                $query->with(['aiModel', 'template', 'responsavel']);
            },
            'extractions' => function($query) {
                $query->with(['aiModel', 'template', 'responsavel']);
            },
            'extractionData'
        ]);

        // Load studies with their relationships
        $allStudies = $project->studies()->with([
            'inclusionCriterias',
            'exclusionCriterias',
            'extractions'
        ])->get();

        // Calculate statistics
        $duplicatesIncluded = $allStudies->where('status_duplicados_exclusao', true)->count();
        $duplicatesExcluded = $allStudies->where('status_duplicados_exclusao', false)->count();
        
        $initialIncluded = $allStudies->where('status_selecao_inicial', true)->count();
        $initialExcluded = $allStudies->where('status_selecao_inicial', false)->count();
        
        $finalIncluded = $allStudies->where('status_selecao_final', true)->count();
        $finalExcluded = $allStudies->where('status_selecao_final', false)->count();

        return view('content.projects.report_generate', compact(
            'project',
            'allStudies',
            'duplicatesIncluded',
            'duplicatesExcluded',
            'initialIncluded',
            'initialExcluded',
            'finalIncluded',
            'finalExcluded'
        ));
    }

    public function printReport(Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Load all necessary relationships (same as generateReport)
        $project->load([
            'researchQuestions',
            'searchStrings',
            'inclusionCriterias',
            'exclusionCriterias',
            'collaborators',
            'studies',
            'initialScreenings' => function($query) {
                $query->with(['aiModel', 'template', 'responsavel']);
            },
            'finalScreenings' => function($query) {
                $query->with(['aiModel', 'template', 'responsavel']);
            },
            'extractions' => function($query) {
                $query->with(['aiModel', 'template', 'responsavel']);
            },
            'extractionData'
        ]);

        // Load studies with their relationships
        $allStudies = $project->studies()->with([
            'inclusionCriterias',
            'exclusionCriterias',
            'extractions'
        ])->get();

        // Calculate statistics
        $duplicatesIncluded = $allStudies->where('status_duplicados_exclusao', true)->count();
        $duplicatesExcluded = $allStudies->where('status_duplicados_exclusao', false)->count();
        
        $initialIncluded = $allStudies->where('status_selecao_inicial', true)->count();
        $initialExcluded = $allStudies->where('status_selecao_inicial', false)->count();
        
        $finalIncluded = $allStudies->where('status_selecao_final', true)->count();
        $finalExcluded = $allStudies->where('status_selecao_final', false)->count();

        return view('content.projects.report_print', compact(
            'project',
            'allStudies',
            'duplicatesIncluded',
            'duplicatesExcluded',
            'initialIncluded',
            'initialExcluded',
            'finalIncluded',
            'finalExcluded'
        ));
    }

    /**
     * Store a newly created study.
     */
    public function store(Request $request, Project $project)
    {
        // Only the owner can create studies
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:30',
            'name' => 'required|string|max:255',
            'year' => 'nullable|sometimes|integer|min:1900|max:2100',
            'authors' => 'nullable|string',
            'document_type' => 'nullable|string|max:255',
            'journal_conference' => 'nullable|string|max:255',
            'tags' => 'nullable|string',
            'notes' => 'nullable|string',
            'doi' => 'nullable|string|max:255',
            'keywords' => 'nullable|string',
            'source' => 'nullable|string',
            'abstract' => 'nullable|string',
            'link' => 'nullable|url|max:500',
            'status_duplicados_exclusao' => 'nullable|boolean',
            'status_selecao_inicial' => 'nullable|boolean',
            'status_selecao_final' => 'nullable|boolean',
            'status_extracao' => 'nullable|boolean',
            'pdf' => 'nullable|file|mimes:pdf|max:51200', // Max 50MB
        ]);

        try {
            // Generate custom_id if not provided
            if (empty($validated['custom_id'])) {
                $validated['custom_id'] = Study::generateNextCustomId($project->id);
            }

            // Set registration method to Manual for form submissions
            $validated['registration_method'] = 'Manual';

            // Handle PDF upload
            if ($request->hasFile('pdf')) {
                try {
                    $file = $request->file('pdf');
                    $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $path = 'studies/' . $project->id . '/' . $filename;
                    
                    Storage::disk()->putFileAs('studies/' . $project->id, $file, $filename, 'public');
                    $validated['pdf_path'] = $path;
                    
                    Log::info('Study PDF uploaded successfully', [
                        'path' => $path,
                        'project_id' => $project->id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Study PDF upload failed', [
                        'error' => $e->getMessage(),
                        'project_id' => $project->id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload PDF: ' . $e->getMessage()
                    ], 500);
                }
            }

            // Remove 'pdf' from validated array (it's not a database field)
            unset($validated['pdf']);

            // Convert empty strings to null for nullable fields
            $validated['year'] = empty($validated['year']) ? null : $validated['year'];
            $validated['authors'] = empty($validated['authors']) ? null : $validated['authors'];
            $validated['document_type'] = empty($validated['document_type']) ? null : $validated['document_type'];
            $validated['journal_conference'] = empty($validated['journal_conference']) ? null : $validated['journal_conference'];
            $validated['tags'] = empty($validated['tags']) ? null : $validated['tags'];
            $validated['notes'] = empty($validated['notes']) ? null : $validated['notes'];

            $study = $project->studies()->create($validated);
            $study->refresh();

            // Clear menu cache for the current user
            Cache::forget('menu_data_user_' . Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Study created successfully',
                'study' => $study
            ]);
        } catch (\Exception $e) {
            Log::error('StudyController@store Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating study. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified study.
     */
    public function update(Request $request, Project $project, Study $study)
    {
        // Collaborators can update studies
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the study belongs to the project
        if ($study->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:30',
            'name' => 'required|string|max:255',
            'year' => 'nullable|sometimes|integer|min:1900|max:2100',
            'authors' => 'nullable|string',
            'document_type' => 'nullable|string|max:255',
            'journal_conference' => 'nullable|string|max:255',
            'tags' => 'nullable|string',
            'notes' => 'nullable|string',
            'doi' => 'nullable|string|max:255',
            'keywords' => 'nullable|string',
            'source' => 'nullable|string',
            'abstract' => 'nullable|string',
            'link' => 'nullable|url|max:500',
            'status_duplicados_exclusao' => 'nullable|boolean',
            'status_selecao_inicial' => 'nullable|boolean',
            'status_selecao_final' => 'nullable|boolean',
            'status_extracao' => 'nullable|boolean',
            'pdf' => 'nullable|file|mimes:pdf|max:51200', // Max 50MB
        ]);

        try {
            // Handle PDF upload (replace existing if new one is uploaded)
            if ($request->hasFile('pdf')) {
                try {
                    // Delete old PDF if exists
                    if ($study->pdf_path) {
                        try {
                            Storage::disk()->delete($study->pdf_path);
                            Log::info('Old study PDF deleted', ['path' => $study->pdf_path]);
                        } catch (\Exception $e) {
                            Log::warning('Could not delete old study PDF: ' . $e->getMessage());
                        }
                    }

                    $file = $request->file('pdf');
                    $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $path = 'studies/' . $project->id . '/' . $filename;
                    
                    Storage::disk()->putFileAs('studies/' . $project->id, $file, $filename, 'public');
                    $validated['pdf_path'] = $path;
                    
                    Log::info('Study PDF uploaded successfully', [
                        'path' => $path,
                        'study_id' => $study->id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Study PDF upload failed', [
                        'error' => $e->getMessage(),
                        'study_id' => $study->id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload PDF: ' . $e->getMessage()
                    ], 500);
                }
            }

            // Remove 'pdf' from validated array
            unset($validated['pdf']);

            // Convert empty strings to null for nullable fields
            if (isset($validated['year'])) {
                $validated['year'] = ($validated['year'] === '' || $validated['year'] === null) ? null : (int)$validated['year'];
            }
            if (isset($validated['authors'])) {
                $validated['authors'] = ($validated['authors'] === '' || $validated['authors'] === null) ? null : $validated['authors'];
            }
            if (isset($validated['document_type'])) {
                $validated['document_type'] = ($validated['document_type'] === '' || $validated['document_type'] === null) ? null : $validated['document_type'];
            }
            if (isset($validated['journal_conference'])) {
                $validated['journal_conference'] = ($validated['journal_conference'] === '' || $validated['journal_conference'] === null) ? null : $validated['journal_conference'];
            }
            if (isset($validated['tags'])) {
                $validated['tags'] = ($validated['tags'] === '' || $validated['tags'] === null) ? null : $validated['tags'];
            }
            if (isset($validated['notes'])) {
                $validated['notes'] = ($validated['notes'] === '' || $validated['notes'] === null) ? null : $validated['notes'];
            }

            $study->update($validated);
            $study->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Study updated successfully',
                'study' => $study
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('StudyController@update Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'study_id' => $study->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error updating study: ' . (config('app.debug') ? $e->getMessage() : 'Please try again.'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified study.
     */
    public function destroy(Project $project, Study $study)
    {
        // Only the owner can delete studies
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the study belongs to the project
        if ($study->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Delete PDF from S3 if exists
            if ($study->pdf_path) {
                try {
                    Storage::disk()->delete($study->pdf_path);
                    Log::info('Study PDF deleted', ['path' => $study->pdf_path]);
                } catch (\Exception $e) {
                    Log::warning('Could not delete study PDF: ' . $e->getMessage());
                }
            }

            $study->delete();

            return response()->json([
                'success' => true,
                'message' => 'Study deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('StudyController@destroy Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting study. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Handle bulk PDF upload
     */
    public function bulkUpload(Request $request, Project $project)
    {
        // Only the owner can bulk upload
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'pdfs' => 'required|array|min:1',
            'pdfs.*' => 'required|file|mimes:pdf|max:51200', // Max 50MB per file
        ]);

        try {
            $results = [
                'assigned' => [],
                'unassigned' => []
            ];

            $files = $request->file('pdfs');
            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }
                
                $filename = $file->getClientOriginalName();
                $basename = pathinfo($filename, PATHINFO_FILENAME); // Remove extension
                
                // Try to find study by custom_id matching the filename (without extension)
                $study = $project->studies()
                    ->where('custom_id', $basename)
                    ->first();

                if ($study) {
                    // A file named after an excluded duplicate belongs to the record that moves on
                    $study = $study->keptDuplicate() ?? $study;

                    // Delete old PDF if exists
                    if ($study->pdf_path) {
                        try {
                            Storage::disk()->delete($study->pdf_path);
                        } catch (\Exception $e) {
                            Log::warning('Could not delete old PDF: ' . $e->getMessage());
                        }
                    }

                    // Upload new PDF
                    $uploadFilename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $path = 'studies/' . $project->id . '/' . $uploadFilename;
                    
                    Storage::disk()->putFileAs('studies/' . $project->id, $file, $uploadFilename);
                    
                    $study->pdf_path = $path;
                    $study->save();

                    $results['assigned'][] = [
                        'filename' => $filename,
                        'study_id' => $study->id,
                        'study_custom_id' => $study->custom_id,
                        'study_name' => $study->name
                    ];
                } else {
                    // Upload PDF temporarily and store for manual assignment
                    $uploadFilename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $path = 'studies/' . $project->id . '/temp/' . $uploadFilename;
                    
                    Storage::disk()->putFileAs('studies/' . $project->id . '/temp', $file, $uploadFilename);
                    
                    $results['unassigned'][] = [
                        'filename' => $filename,
                        'temp_path' => $path,
                        'temp_filename' => $uploadFilename
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk upload completed',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('StudyController@bulkUpload Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing bulk upload: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Queue an open-access PDF search (by DOI) for the studies in the final selection
     * (included in the initial selection) that have a DOI and no PDF yet.
     */
    public function fetchOpenAccessPdfs(Request $request, Project $project)
    {
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'unpaywall_email' => 'nullable|email|max:255',
            'core_api_key' => 'nullable|string|max:255',
            'clear_core_api_key' => 'nullable|boolean',
        ]);

        // Remember the user's contact e-mail and CORE key for the next searches (the jobs read
        // them from the account); an empty key field keeps the saved key unless cleared
        $user = Auth::user();
        if ($request->has('unpaywall_email')) {
            $user->unpaywall_email = $validated['unpaywall_email'] ?: null;
        }
        if (!empty($validated['clear_core_api_key'])) {
            $user->core_api_key = null;
        } elseif (!empty($validated['core_api_key'])) {
            $user->core_api_key = trim($validated['core_api_key']);
        }
        $user->save();

        $key = \App\Jobs\DownloadStudyPdf::progressKey($project->id);
        $progress = Cache::get($key);
        if ($progress && $progress['done'] < $progress['total']) {
            return response()->json([
                'success' => false,
                'message' => 'A PDF search is already running for this project.',
                'progress' => $progress,
            ], 409);
        }

        $studies = $project->studies()
            ->where('status_selecao_inicial', 1)
            ->where(fn ($q) => $q->whereNull('pdf_path')->orWhere('pdf_path', ''))
            ->whereNotNull('doi')
            ->get()
            ->filter(fn (Study $study) => \App\Services\OpenAccessPdf::normalizeDoi($study->doi) !== null);

        if ($studies->isEmpty()) {
            Cache::forget($key);
            return response()->json([
                'success' => true,
                'message' => 'No studies in the final selection with a DOI and without a PDF.',
                'progress' => null,
                'eligible' => $this->openAccessPdfCounts($project),
            ]);
        }

        $progress = \App\Jobs\DownloadStudyPdf::startProgress($project->id, $studies, $this->openAccessSources($user));
        foreach ($studies as $study) {
            \App\Jobs\DownloadStudyPdf::dispatch($study);
        }

        return response()->json([
            'success' => true,
            'message' => "Searching open-access PDFs for {$progress['total']} studies.",
            'progress' => $progress,
        ]);
    }

    /**
     * Progress of the project's open-access PDF search (null when none was started).
     */
    public function fetchOpenAccessPdfsStatus(Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        return response()->json([
            'progress' => Cache::get(\App\Jobs\DownloadStudyPdf::progressKey($project->id)),
            'eligible' => $this->openAccessPdfCounts($project),
            'unpaywall_email' => Auth::user()->unpaywall_email,
            'unpaywall_server_email' => (bool) config('services.pdf_download.unpaywall_email'),
            'core_api_key_saved' => !empty(Auth::user()->core_api_key),
            'core_server_key' => (bool) config('services.pdf_download.core_api_key'),
        ]);
    }

    /**
     * Names of the open-access sources a PDF search by this user queries.
     */
    private function openAccessSources(\App\Models\User $user): array
    {
        $sources = ['OpenAlex', 'Crossref', 'Europe PMC', 'DOI page'];
        if ($user->unpaywall_email || config('services.pdf_download.unpaywall_email')) {
            array_splice($sources, 1, 0, 'Unpaywall');
        }
        if ($user->core_api_key || config('services.pdf_download.core_api_key')) {
            array_splice($sources, -1, 0, 'CORE');
        }

        return $sources;
    }

    /**
     * Counts of the final selection's studies by PDF and DOI, for the PDF search window.
     */
    private function openAccessPdfCounts(Project $project): array
    {
        $studies = $project->studies()->where('status_selecao_inicial', 1)->get(['id', 'doi', 'pdf_path']);
        $withPdf = $studies->filter(fn ($s) => !empty($s->pdf_path));
        $withoutPdf = $studies->reject(fn ($s) => !empty($s->pdf_path));
        $searchable = $withoutPdf->filter(fn ($s) => \App\Services\OpenAccessPdf::normalizeDoi($s->doi) !== null);

        return [
            'final_selection' => $studies->count(),
            'with_pdf' => $withPdf->count(),
            'searchable' => $searchable->count(),
            'without_doi' => $withoutPdf->count() - $searchable->count(),
        ];
    }

    /**
     * Assign a PDF to a study manually
     */
    public function assignPdf(Request $request, Project $project)
    {
        // Only the owner can assign PDFs
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'study_id' => 'required|exists:studies,id',
            'temp_path' => 'required|string',
            'temp_filename' => 'required|string',
        ]);

        // Ensure the study belongs to the project
        $study = $project->studies()->findOrFail($validated['study_id']);

        try {
            // Check if temp file exists
            if (!Storage::disk()->exists($validated['temp_path'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Temporary file not found'
                ], 404);
            }

            // Delete old PDF if exists
            if ($study->pdf_path) {
                try {
                    Storage::disk()->delete($study->pdf_path);
                } catch (\Exception $e) {
                    Log::warning('Could not delete old PDF: ' . $e->getMessage());
                }
            }

            // Move file from temp to final location
            $finalPath = 'studies/' . $project->id . '/' . $validated['temp_filename'];
            $fileContent = Storage::disk()->get($validated['temp_path']);
            Storage::disk()->put($finalPath, $fileContent, 'public');
            
            // Delete temp file
            Storage::disk()->delete($validated['temp_path']);

            // Update study
            $study->pdf_path = $finalPath;
            $study->save();

            return response()->json([
                'success' => true,
                'message' => 'PDF assigned successfully',
                'study' => $study
            ]);
        } catch (\Exception $e) {
            Log::error('StudyController@assignPdf Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error assigning PDF: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Import studies from Excel file
     */
    public function importExcel(Request $request, Project $project)
    {
        // Only the owner can import Excel
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
            'search_string_id' => [
                'required',
                Rule::exists('search_strings', 'id')->where('project_id', $project->id),
            ],
        ], [
            'search_string_id.required' => 'Select the database the file was exported from.',
            'search_string_id.exists' => 'The selected database does not belong to this project.',
        ]);

        // Every imported study is linked to the database (search string) the file came from
        $searchString = $project->searchStrings()->findOrFail($request->input('search_string_id'));

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows) || count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Get header row (first row) - normalize to lowercase for case-insensitive matching
            $headers = array_map(function($header) {
                return strtolower(trim($header));
            }, $rows[0]);
            
            // Map column names (case-insensitive, flexible)
            // All headers are already lowercase, so we compare with lowercase values
            $columnMap = [];
            foreach ($headers as $index => $header) {
                $header = strtolower(trim($header));
                
                // Custom ID variations (case-insensitive)
                // Do not treat primary key "id" as custom id during import
                if (in_array($header, ['custom_id', 'custom id', 'customid'])) {
                    $columnMap['custom_id'] = $index;
                } 
                // Name or Title variations (case-insensitive) - accepts both "name" and "title"
                elseif (in_array($header, ['name', 'nome', 'title', 'titulo', 'título'])) {
                    $columnMap['name'] = $index;
                } 
                // DOI variations (case-insensitive)
                elseif (in_array($header, ['doi', 'dois'])) {
                    $columnMap['doi'] = $index;
                } 
                // Keywords variations (case-insensitive)
                elseif (in_array($header, ['keywords', 'keyword', 'palavras-chave', 'palavras chave', 'palavraschave'])) {
                    $columnMap['keywords'] = $index;
                } 
                // Source variations (case-insensitive)
                elseif (in_array($header, ['source', 'fonte', 'origem', 'database', 'base de dados'])) {
                    $columnMap['source'] = $index;
                } 
                // Abstract variations (case-insensitive)
                elseif (in_array($header, ['abstract', 'resumo', 'summary', 'resumen'])) {
                    $columnMap['abstract'] = $index;
                } 
                // Link variations (case-insensitive)
                elseif (in_array($header, ['link', 'url', 'link_url', 'linkurl', 'hyperlink'])) {
                    $columnMap['link'] = $index;
                }
                // Year variations (case-insensitive)
                elseif (in_array($header, ['year', 'ano', 'year published', 'publication year', 'pub year'])) {
                    $columnMap['year'] = $index;
                }
                // Authors variations (case-insensitive)
                elseif (in_array($header, ['authors', 'author', 'autores', 'autor', 'writer', 'writers'])) {
                    $columnMap['authors'] = $index;
                }
                // Document Type variations (case-insensitive)
                elseif (in_array($header, ['document type', 'document_type', 'documenttype', 'type', 'doc type', 'publication type', 'pub type'])) {
                    $columnMap['document_type'] = $index;
                }
                // Journal/Conference variations (case-insensitive)
                elseif (in_array($header, ['journal/conference', 'journal_conference', 'journalconference', 'journal', 'conference', 'venue', 'publication venue'])) {
                    $columnMap['journal_conference'] = $index;
                }
                // Tags variations (case-insensitive)
                elseif (in_array($header, ['tags', 'tag', 'etiquetas', 'etiqueta'])) {
                    $columnMap['tags'] = $index;
                }
                // Notes variations (case-insensitive)
                elseif (in_array($header, ['notes', 'note', 'notas', 'nota', 'comments', 'comment', 'observations', 'observation'])) {
                    $columnMap['notes'] = $index;
                }
                // Duplicates and exclusion criteria variations (case-insensitive)
                elseif (in_array($header, ['duplicates and exclusion criteria', 'duplicates_exclusion_criteria', 'duplicatesexclusioncriteria', 'duplicados e criterios de exclusao', 'status_duplicados_exclusao', 'duplicates exclusion'])) {
                    $columnMap['status_duplicados_exclusao'] = $index;
                }
                // Initial selection variations (case-insensitive)
                elseif (in_array($header, ['initial selection', 'initial_selection', 'initialselection', 'selecao inicial', 'status_selecao_inicial', 'initial'])) {
                    $columnMap['status_selecao_inicial'] = $index;
                }
                // Final selection variations (case-insensitive)
                elseif (in_array($header, ['final selection', 'final_selection', 'finalselection', 'selecao final', 'status_selecao_final', 'final'])) {
                    $columnMap['status_selecao_final'] = $index;
                }
                // Extraction variations (case-insensitive)
                elseif (in_array($header, ['extraction', 'extração', 'extraccao', 'status_extracao', 'extract'])) {
                    $columnMap['status_extracao'] = $index;
                }
            }

            if (!isset($columnMap['name'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Required column "name" or "title" not found in Excel file'
                ], 422);
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            // Process data rows (skip header row)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $studyData = [
                    'project_id' => $project->id,
                    'registration_method' => 'XLS/CSV',
                ];
                $studyData['search_string_id'] = $searchString->id;

                // Extract data based on column map
                if (isset($columnMap['custom_id']) && isset($row[$columnMap['custom_id']])) {
                    $customId = trim($row[$columnMap['custom_id']]);
                    if (!empty($customId)) {
                        $studyData['custom_id'] = substr($customId, 0, 30); // Max 30 chars
                    }
                }

                if (isset($columnMap['name']) && isset($row[$columnMap['name']])) {
                    $name = trim($row[$columnMap['name']]);
                    if (empty($name)) {
                        $skipped++;
                        $errors[] = "Row " . ($i + 1) . ": Name is required";
                        continue;
                    }
                    $studyData['name'] = $name;
                } else {
                    $skipped++;
                    $errors[] = "Row " . ($i + 1) . ": Name is required";
                    continue;
                }

                if (isset($columnMap['doi']) && isset($row[$columnMap['doi']])) {
                    $doi = trim($row[$columnMap['doi']]);
                    if (!empty($doi)) {
                        $studyData['doi'] = $doi;
                    }
                }

                if (isset($columnMap['keywords']) && isset($row[$columnMap['keywords']])) {
                    $keywords = trim($row[$columnMap['keywords']]);
                    if (!empty($keywords)) {
                        $studyData['keywords'] = $keywords;
                    }
                }

                // The selected database wins over any "source" column
                $studyData['source'] = $searchString->database;

                if (isset($columnMap['abstract']) && isset($row[$columnMap['abstract']])) {
                    $abstract = trim($row[$columnMap['abstract']]);
                    if (!empty($abstract)) {
                        $studyData['abstract'] = $abstract;
                    }
                }

                if (isset($columnMap['link']) && isset($row[$columnMap['link']])) {
                    $link = trim($row[$columnMap['link']]);
                    if (!empty($link)) {
                        $studyData['link'] = $link;
                    }
                }

                // Year
                if (isset($columnMap['year']) && isset($row[$columnMap['year']])) {
                    $year = trim($row[$columnMap['year']]);
                    if (!empty($year) && is_numeric($year)) {
                        $yearInt = (int)$year;
                        if ($yearInt >= 1900 && $yearInt <= 2100) {
                            $studyData['year'] = $yearInt;
                        }
                    }
                }

                // Authors
                if (isset($columnMap['authors']) && isset($row[$columnMap['authors']])) {
                    $authors = trim($row[$columnMap['authors']]);
                    if (!empty($authors)) {
                        $studyData['authors'] = $authors;
                    }
                }

                // Document Type
                if (isset($columnMap['document_type']) && isset($row[$columnMap['document_type']])) {
                    $documentType = trim($row[$columnMap['document_type']]);
                    if (!empty($documentType)) {
                        $studyData['document_type'] = substr($documentType, 0, 255);
                    }
                }

                // Journal/Conference
                if (isset($columnMap['journal_conference']) && isset($row[$columnMap['journal_conference']])) {
                    $journalConference = trim($row[$columnMap['journal_conference']]);
                    if (!empty($journalConference)) {
                        $studyData['journal_conference'] = substr($journalConference, 0, 255);
                    }
                }

                // Tags
                if (isset($columnMap['tags']) && isset($row[$columnMap['tags']])) {
                    $tags = trim($row[$columnMap['tags']]);
                    if (!empty($tags)) {
                        $studyData['tags'] = $tags;
                    }
                }

                // Notes
                if (isset($columnMap['notes']) && isset($row[$columnMap['notes']])) {
                    $notes = trim($row[$columnMap['notes']]);
                    if (!empty($notes)) {
                        $studyData['notes'] = $notes;
                    }
                }

                // Status: Duplicates and exclusion criteria
                if (isset($columnMap['status_duplicados_exclusao']) && isset($row[$columnMap['status_duplicados_exclusao']])) {
                    $statusValue = trim(strtolower($row[$columnMap['status_duplicados_exclusao']]));
                    if (!empty($statusValue)) {
                        if (in_array($statusValue, ['1', 'true', 'yes', 'sim', 'y'])) {
                            $studyData['status_duplicados_exclusao'] = true;
                        } elseif (in_array($statusValue, ['0', 'false', 'no', 'não', 'nao', 'n'])) {
                            $studyData['status_duplicados_exclusao'] = false;
                        }
                    }
                }

                // Status: Initial selection
                if (isset($columnMap['status_selecao_inicial']) && isset($row[$columnMap['status_selecao_inicial']])) {
                    $statusValue = trim(strtolower($row[$columnMap['status_selecao_inicial']]));
                    if (!empty($statusValue)) {
                        if (in_array($statusValue, ['1', 'true', 'yes', 'sim', 'y'])) {
                            $studyData['status_selecao_inicial'] = true;
                        } elseif (in_array($statusValue, ['0', 'false', 'no', 'não', 'nao', 'n'])) {
                            $studyData['status_selecao_inicial'] = false;
                        }
                    }
                }

                // Status: Final selection
                if (isset($columnMap['status_selecao_final']) && isset($row[$columnMap['status_selecao_final']])) {
                    $statusValue = trim(strtolower($row[$columnMap['status_selecao_final']]));
                    if (!empty($statusValue)) {
                        if (in_array($statusValue, ['1', 'true', 'yes', 'sim', 'y'])) {
                            $studyData['status_selecao_final'] = true;
                        } elseif (in_array($statusValue, ['0', 'false', 'no', 'não', 'nao', 'n'])) {
                            $studyData['status_selecao_final'] = false;
                        }
                    }
                }

                // Status: Extraction
                if (isset($columnMap['status_extracao']) && isset($row[$columnMap['status_extracao']])) {
                    $statusValue = trim(strtolower($row[$columnMap['status_extracao']]));
                    if (!empty($statusValue)) {
                        if (in_array($statusValue, ['1', 'true', 'yes', 'sim', 'y'])) {
                            $studyData['status_extracao'] = true;
                        } elseif (in_array($statusValue, ['0', 'false', 'no', 'não', 'nao', 'n'])) {
                            $studyData['status_extracao'] = false;
                        }
                    }
                }

                // Generate custom_id if not provided
                if (empty($studyData['custom_id'])) {
                    $studyData['custom_id'] = Study::generateNextCustomId($project->id);
                }

                try {
                    Study::create($studyData);
                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Row " . ($i + 1) . ": " . $e->getMessage();
                    Log::error('Error importing study from Excel row ' . ($i + 1), [
                        'error' => $e->getMessage(),
                        'data' => $studyData
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Import completed from {$searchString->database}: {$imported} studies imported, {$skipped} skipped",
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            Log::error('StudyController@importExcel Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error importing Excel file: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Import studies from a BibTeX (.bib) or RIS (.ris) export, linking each one to the
     * search string (database) the file came from.
     */
    public function importBibliography(Request $request, Project $project, BibliographyParser $parser)
    {
        // Only the owner can import, as with Excel
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'bibliography_file' => 'required|file|max:10240', // Max 10MB
            'search_string_id' => [
                'required',
                Rule::exists('search_strings', 'id')->where('project_id', $project->id),
            ],
        ], [
            'search_string_id.required' => 'Select the database the file was exported from.',
            'search_string_id.exists' => 'The selected database does not belong to this project.',
        ]);

        $file = $request->file('bibliography_file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['bib', 'bibtex', 'ris', 'txt'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported file type. Use a BibTeX (.bib) or RIS (.ris) file.',
            ], 422);
        }

        try {
            $content = file_get_contents($file->getRealPath());
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
            }

            $format = $parser->detectFormat($content, $extension);
            if ($format === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'The file is not a BibTeX or RIS export.',
                ], 422);
            }

            $records = $parser->parse($content, $format);
            if (empty($records)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No references with a title were found in the file.',
                ], 422);
            }

            $searchString = SearchString::find($request->input('search_string_id'));

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($records as $index => $record) {
                $studyData = array_filter($record, fn ($value) => $value !== null) + [
                    'project_id' => $project->id,
                    'search_string_id' => $searchString->id,
                    'source' => $searchString->database,
                    'registration_method' => $format === 'ris' ? 'RIS' : 'BibTeX',
                    'custom_id' => Study::generateNextCustomId($project->id),
                ];

                try {
                    Study::create($studyData);
                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = 'Reference ' . ($index + 1) . ': ' . $e->getMessage();
                    Log::error('Error importing study from ' . $format . ' reference ' . ($index + 1), [
                        'error' => $e->getMessage(),
                        'data' => $studyData,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Import completed from {$searchString->database}: {$imported} studies imported, {$skipped} skipped",
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            Log::error('StudyController@importBibliography Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error importing file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete duplicate studies (keep first occurrence, exclude rest).
     */
    public function deleteDuplicates(Request $request, Project $project)
    {
        // Only the owner can delete duplicates
        if (!$this->isOwner($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // 1. Find or create the "Duplicate Study" ExclusionCriteria
            $exclusionCriteria = ExclusionCriteria::where('project_id', $project->id)
                ->where('exclusion_criteria', 'Duplicate Study')
                ->first();

            if (!$exclusionCriteria) {
                $exclusionCriteria = ExclusionCriteria::create([
                    'project_id' => $project->id,
                    'custom_id' => ExclusionCriteria::generateNextCustomId($project->id),
                    'exclusion_criteria' => 'Duplicate Study',
                ]);
            }

            // 2. Get all studies for this project ordered by id (to keep first occurrence)
            $studies = Study::where('project_id', $project->id)
                ->orderBy('id', 'asc')
                ->get();

            // 3. Track unique values to identify duplicates
            $seenNames = [];
            $seenDois = [];
            $seenLinks = [];
            $duplicateIds = [];
            $keptIds = [];

            foreach ($studies as $study) {
                $isDuplicate = false;
                $nameKey = $study->name ? trim(strtolower($study->name)) : null;
                $doiKey = $study->doi ? trim(strtolower($study->doi)) : null;
                $linkKey = $study->link ? trim(strtolower($study->link)) : null;

                // Check for duplicate name
                if ($nameKey && !empty($nameKey)) {
                    if (isset($seenNames[$nameKey])) {
                        $isDuplicate = true;
                    } else {
                        $seenNames[$nameKey] = $study->id;
                    }
                }

                // Check for duplicate DOI
                if (!$isDuplicate && $doiKey && !empty($doiKey)) {
                    if (isset($seenDois[$doiKey])) {
                        $isDuplicate = true;
                    } else {
                        $seenDois[$doiKey] = $study->id;
                    }
                }

                // Check for duplicate link
                if (!$isDuplicate && $linkKey && !empty($linkKey)) {
                    if (isset($seenLinks[$linkKey])) {
                        $isDuplicate = true;
                    } else {
                        $seenLinks[$linkKey] = $study->id;
                    }
                }

                if ($isDuplicate) {
                    $duplicateIds[] = $study->id;
                } else {
                    $keptIds[] = $study->id;
                }
            }

            // 4. Mark duplicates as excluded and add to pivot table
            $excludedCount = 0;
            foreach ($duplicateIds as $studyId) {
                // Update study status
                Study::where('id', $studyId)->update([
                    'status_duplicados_exclusao' => false
                ]);

                // Add to pivot table (check if not already exists)
                $exists = DB::table('study_exclusion_criteria')
                    ->where('study_id', $studyId)
                    ->where('exclusion_criteria_id', $exclusionCriteria->id)
                    ->where('type', 'duplicates')
                    ->exists();

                if (!$exists) {
                    DB::table('study_exclusion_criteria')->insert([
                        'study_id' => $studyId,
                        'exclusion_criteria_id' => $exclusionCriteria->id,
                        'type' => 'duplicates',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $excludedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Duplicate removal completed: {$excludedCount} studies marked as excluded, " . count($keptIds) . " studies kept.",
                'excluded_count' => $excludedCount,
                'kept_count' => count($keptIds),
                'exclusion_criteria_id' => $exclusionCriteria->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StudyController@deleteDuplicates Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error removing duplicates: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Include all studies with null status (N/A).
     */
    public function includeNa(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            // Update all studies with null status to included (true)
            $updatedCount = Study::where('project_id', $project->id)
                ->whereNull('status_duplicados_exclusao')
                ->update(['status_duplicados_exclusao' => true]);

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} studies marked as included.",
                'included_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('StudyController@includeNa Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error including studies: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Download included studies as Excel file.
     */
    public function downloadIncluded(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Get all studies marked as included (status_duplicados_exclusao = true)
        $studies = Study::where('project_id', $project->id)
            ->where('status_duplicados_exclusao', true)
            ->orderBy('custom_id', 'asc')
            ->get(['custom_id', 'name', 'doi', 'abstract', 'link']);

        // Create spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Included Studies');

        // Set headers
        $headers = ['ID', 'Title', 'DOI', 'Abstract', 'Link'];
        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        // Add data
        $row = 2;
        foreach ($studies as $study) {
            $sheet->setCellValue('A' . $row, $study->custom_id ?? 'N/A');
            $sheet->setCellValue('B' . $row, $study->name ?? 'N/A');
            $sheet->setCellValue('C' . $row, $study->doi ?? 'N/A');
            $sheet->setCellValue('D' . $row, $study->abstract ?? 'N/A');
            $sheet->setCellValue('E' . $row, $study->link ?? 'N/A');
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set column D (Abstract) to a reasonable width
        $sheet->getColumnDimension('D')->setAutoSize(false);
        $sheet->getColumnDimension('D')->setWidth(60);
        $sheet->getStyle('D2:D' . ($row - 1))->getAlignment()->setWrapText(true);

        // Create the response
        $filename = 'included_studies_' . $project->id . '_' . date('Y-m-d_His') . '.xlsx';
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Save to temp file and return as download
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Update duplicates status and criteria for a study.
     */
    public function updateDuplicatesStatus(Request $request, Project $project, Study $study)
    {
        // Collaborators can update status
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        // Ensure the study belongs to the project
        if ($study->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Study does not belong to this project.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $status = $request->input('status'); // 'include', 'exclude', or null
            $criteriaIds = $request->input('criteria_ids', []);

            // Update study status
            if ($status === 'include') {
                $study->status_duplicados_exclusao = true;
            } elseif ($status === 'exclude') {
                $study->status_duplicados_exclusao = false;
            } else {
                $study->status_duplicados_exclusao = null;
            }
            $study->save();

            // Clear existing criteria relationships for this study with type 'duplicates'
            DB::table('study_inclusion_criteria')
                ->where('study_id', $study->id)
                ->where('type', 'duplicates')
                ->delete();

            DB::table('study_exclusion_criteria')
                ->where('study_id', $study->id)
                ->where('type', 'duplicates')
                ->delete();

            // Add new criteria relationships based on status
            if ($status === 'include' && !empty($criteriaIds)) {
                foreach ($criteriaIds as $criteriaId) {
                    DB::table('study_inclusion_criteria')->insert([
                        'study_id' => $study->id,
                        'inclusion_criteria_id' => $criteriaId,
                        'type' => 'duplicates',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($status === 'exclude' && !empty($criteriaIds)) {
                foreach ($criteriaIds as $criteriaId) {
                    DB::table('study_exclusion_criteria')->insert([
                        'study_id' => $study->id,
                        'exclusion_criteria_id' => $criteriaId,
                        'type' => 'duplicates',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Study updated successfully.',
                'status' => $status,
                'criteria_count' => count($criteriaIds)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StudyController@updateDuplicatesStatus Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating study: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Include all studies with null initial selection status (N/A).
     * Only considers studies that were included in duplicates phase.
     */
    public function includeInitialNa(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            // Update all studies with null status_selecao_inicial to included (true)
            // Only for studies that were included in duplicates phase
            $updatedCount = Study::where('project_id', $project->id)
                ->where('status_duplicados_exclusao', true)
                ->whereNull('status_selecao_inicial')
                ->update(['status_selecao_inicial' => true]);

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} studies marked as included in initial selection.",
                'included_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('StudyController@includeInitialNa Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error including studies: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Download initial selection included studies as Excel file.
     */
    public function downloadInitialIncluded(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Get all studies marked as included in initial selection (status_selecao_inicial = true)
        // They must also have been included in duplicates phase
        $studies = Study::where('project_id', $project->id)
            ->where('status_duplicados_exclusao', true)
            ->where('status_selecao_inicial', true)
            ->orderBy('custom_id', 'asc')
            ->get(['custom_id', 'name', 'doi', 'abstract', 'link']);

        // Create spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Initial Selection');

        // Set headers
        $headers = ['ID', 'Title', 'DOI', 'Abstract', 'Link'];
        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '70AD47']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        // Add data
        $row = 2;
        foreach ($studies as $study) {
            $sheet->setCellValue('A' . $row, $study->custom_id ?? 'N/A');
            $sheet->setCellValue('B' . $row, $study->name ?? 'N/A');
            $sheet->setCellValue('C' . $row, $study->doi ?? 'N/A');
            $sheet->setCellValue('D' . $row, $study->abstract ?? 'N/A');
            $sheet->setCellValue('E' . $row, $study->link ?? 'N/A');
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set column D (Abstract) to a reasonable width
        $sheet->getColumnDimension('D')->setAutoSize(false);
        $sheet->getColumnDimension('D')->setWidth(60);
        $sheet->getStyle('D2:D' . ($row - 1))->getAlignment()->setWrapText(true);

        // Create the response
        $filename = 'initial_selection_' . $project->id . '_' . date('Y-m-d_His') . '.xlsx';
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Save to temp file and return as download
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Download final selection included studies as Excel file.
     */
    public function downloadFinalIncluded(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Get all studies marked as included in final selection (status_selecao_final = true)
        // They must also have been included in initial selection phase
        $studies = Study::where('project_id', $project->id)
            ->where('status_selecao_inicial', true)
            ->where('status_selecao_final', true)
            ->orderBy('custom_id', 'asc')
            ->get(['custom_id', 'name', 'doi', 'abstract', 'link']);

        // Create spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Final Selection');

        // Set headers
        $headers = ['ID', 'Title', 'DOI', 'Abstract', 'Link'];
        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '70AD47']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        // Add data
        $row = 2;
        foreach ($studies as $study) {
            $sheet->setCellValue('A' . $row, $study->custom_id ?? 'N/A');
            $sheet->setCellValue('B' . $row, $study->name ?? 'N/A');
            $sheet->setCellValue('C' . $row, $study->doi ?? 'N/A');
            $sheet->setCellValue('D' . $row, $study->abstract ?? 'N/A');
            $sheet->setCellValue('E' . $row, $study->link ?? 'N/A');
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set column D (Abstract) to a reasonable width
        $sheet->getColumnDimension('D')->setAutoSize(false);
        $sheet->getColumnDimension('D')->setWidth(60);
        $sheet->getStyle('D2:D' . ($row - 1))->getAlignment()->setWrapText(true);

        // Create the response
        $filename = 'final_selection_' . $project->id . '_' . date('Y-m-d_His') . '.xlsx';
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Save to temp file and return as download
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Update initial selection status and criteria for a study.
     */
    public function updateInitialStatus(Request $request, Project $project, Study $study)
    {
        // Collaborators can update status
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        // Ensure the study belongs to the project
        if ($study->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Study does not belong to this project.'
            ], 403);
        }

        $request->validate(['comment' => 'nullable|string|max:5000']);

        try {
            DB::beginTransaction();

            $status = $request->input('status'); // 'include', 'exclude', or null
            $criteriaIds = $request->input('criteria_ids', []);

            // Update study status
            if ($status === 'include') {
                $study->status_selecao_inicial = true;
            } elseif ($status === 'exclude') {
                $study->status_selecao_inicial = false;
            } else {
                $study->status_selecao_inicial = null;
            }
            if ($request->exists('comment')) {
                $study->comentario_selecao_inicial = $this->decisionComment($request);
            }
            $study->save();

            // Clear existing criteria relationships for this study with type 'initial'
            DB::table('study_inclusion_criteria')
                ->where('study_id', $study->id)
                ->where('type', 'initial')
                ->delete();

            DB::table('study_exclusion_criteria')
                ->where('study_id', $study->id)
                ->where('type', 'initial')
                ->delete();

            // Add new criteria relationships based on status
            if ($status === 'include' && !empty($criteriaIds)) {
                foreach ($criteriaIds as $criteriaId) {
                    DB::table('study_inclusion_criteria')->insert([
                        'study_id' => $study->id,
                        'inclusion_criteria_id' => $criteriaId,
                        'type' => 'initial',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($status === 'exclude' && !empty($criteriaIds)) {
                foreach ($criteriaIds as $criteriaId) {
                    DB::table('study_exclusion_criteria')->insert([
                        'study_id' => $study->id,
                        'exclusion_criteria_id' => $criteriaId,
                        'type' => 'initial',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Study updated successfully.',
                'status' => $status,
                'criteria_count' => count($criteriaIds)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StudyController@updateInitialStatus Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating study: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Optional free-text comment sent with a selection decision (blank clears it).
     */
    private function decisionComment(Request $request): ?string
    {
        $comment = trim((string) $request->input('comment'));

        return $comment === '' ? null : $comment;
    }

    /**
     * Update final selection status and criteria for a study.
     */
    public function updateFinalStatus(Request $request, Project $project, Study $study)
    {
        // Collaborators can update status
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        // Ensure the study belongs to the project
        if ($study->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Study does not belong to this project.'
            ], 403);
        }

        $request->validate(['comment' => 'nullable|string|max:5000']);

        try {
            DB::beginTransaction();

            $status = $request->input('status'); // 'include', 'exclude', or null
            $criteriaIds = $request->input('criteria_ids', []);

            // Update study status
            if ($status === 'include') {
                $study->status_selecao_final = true;
            } elseif ($status === 'exclude') {
                $study->status_selecao_final = false;
            } else {
                $study->status_selecao_final = null;
            }
            if ($request->exists('comment')) {
                $study->comentario_selecao_final = $this->decisionComment($request);
            }
            $study->save();

            // Clear existing criteria relationships for this study with type 'final'
            DB::table('study_inclusion_criteria')
                ->where('study_id', $study->id)
                ->where('type', 'final')
                ->delete();

            DB::table('study_exclusion_criteria')
                ->where('study_id', $study->id)
                ->where('type', 'final')
                ->delete();

            // Add new criteria relationships based on status
            if ($status === 'include' && !empty($criteriaIds)) {
                foreach ($criteriaIds as $criteriaId) {
                    DB::table('study_inclusion_criteria')->insert([
                        'study_id' => $study->id,
                        'inclusion_criteria_id' => $criteriaId,
                        'type' => 'final',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($status === 'exclude' && !empty($criteriaIds)) {
                foreach ($criteriaIds as $criteriaId) {
                    DB::table('study_exclusion_criteria')->insert([
                        'study_id' => $study->id,
                        'exclusion_criteria_id' => $criteriaId,
                        'type' => 'final',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Study updated successfully.',
                'status' => $status,
                'criteria_count' => count($criteriaIds)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StudyController@updateFinalStatus Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating study: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update screening status and criteria for a study.
     */
    public function updateScreeningStatus(Request $request, Project $project, Study $study)
    {
        // Collaborators can update status
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        // Ensure the study belongs to the project
        if ($study->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Study does not belong to this project.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $status = $request->input('status'); // 'include', 'exclude', or null
            $criteriaIds = $request->input('criteria_ids', []);
            $screeningId = $request->input('screening_id');

            if (!$screeningId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Screening ID is required.'
                ], 422);
            }

            // Verify screening belongs to project - check final screening first (since we're in final context)
            $finalScreening = null;
            $isFinalScreening = false;
            
            try {
                $finalScreening = \App\Models\FinalScreening::where('id', $screeningId)
                    ->where('project_id', $project->id)
                    ->first();
                
                if ($finalScreening) {
                    $isFinalScreening = true;
                }
            } catch (\Exception $e) {
                // Table doesn't exist yet, will check initial screening
            }
            
            // If not final, check if it's an initial screening
            if (!$isFinalScreening) {
                $initialScreening = \App\Models\InitialScreening::where('id', $screeningId)
                    ->where('project_id', $project->id)
                    ->first();
                
                if (!$initialScreening) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Screening does not belong to this project.'
                    ], 403);
                }
            }

            // Update or create the status in the pivot table
            $statusValue = null;
            if ($status === 'include') {
                $statusValue = true;
            } elseif ($status === 'exclude') {
                $statusValue = false;
            }

            // Update or create the status in the appropriate pivot table
            if ($isFinalScreening) {
                $exists = DB::table('final_screening_study')
                    ->where('study_id', $study->id)
                    ->where('final_screening_id', $screeningId)
                    ->exists();

                if ($exists) {
                    DB::table('final_screening_study')
                        ->where('study_id', $study->id)
                        ->where('final_screening_id', $screeningId)
                        ->update(['status' => $statusValue, 'updated_at' => now()]);
                } else {
                    DB::table('final_screening_study')->insert([
                        'study_id' => $study->id,
                        'final_screening_id' => $screeningId,
                        'status' => $statusValue,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Clear existing criteria relationships for this study and screening
                DB::table('study_inclusion_criteria_screening')
                    ->where('study_id', $study->id)
                    ->where('final_screening_id', $screeningId)
                    ->delete();

                DB::table('study_exclusion_criteria_screening')
                    ->where('study_id', $study->id)
                    ->where('final_screening_id', $screeningId)
                    ->delete();

                // Add new criteria relationships based on status
                if ($status === 'include' && !empty($criteriaIds)) {
                    foreach ($criteriaIds as $criteriaId) {
                        DB::table('study_inclusion_criteria_screening')->insert([
                            'study_id' => $study->id,
                            'inclusion_criteria_id' => $criteriaId,
                            'final_screening_id' => $screeningId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($status === 'exclude' && !empty($criteriaIds)) {
                    foreach ($criteriaIds as $criteriaId) {
                        DB::table('study_exclusion_criteria_screening')->insert([
                            'study_id' => $study->id,
                            'exclusion_criteria_id' => $criteriaId,
                            'final_screening_id' => $screeningId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } else {
                $exists = DB::table('initial_screening_study')
                    ->where('study_id', $study->id)
                    ->where('initial_screening_id', $screeningId)
                    ->exists();

                if ($exists) {
                    DB::table('initial_screening_study')
                        ->where('study_id', $study->id)
                        ->where('initial_screening_id', $screeningId)
                        ->update(['status' => $statusValue, 'updated_at' => now()]);
                } else {
                    DB::table('initial_screening_study')->insert([
                        'study_id' => $study->id,
                        'initial_screening_id' => $screeningId,
                        'status' => $statusValue,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Clear existing criteria relationships for this study and screening
                DB::table('study_inclusion_criteria_screening')
                    ->where('study_id', $study->id)
                    ->where('initial_screening_id', $screeningId)
                    ->delete();

                DB::table('study_exclusion_criteria_screening')
                    ->where('study_id', $study->id)
                    ->where('initial_screening_id', $screeningId)
                    ->delete();

                // Add new criteria relationships based on status
                if ($status === 'include' && !empty($criteriaIds)) {
                    foreach ($criteriaIds as $criteriaId) {
                        DB::table('study_inclusion_criteria_screening')->insert([
                            'study_id' => $study->id,
                            'inclusion_criteria_id' => $criteriaId,
                            'initial_screening_id' => $screeningId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($status === 'exclude' && !empty($criteriaIds)) {
                    foreach ($criteriaIds as $criteriaId) {
                        DB::table('study_exclusion_criteria_screening')->insert([
                            'study_id' => $study->id,
                            'exclusion_criteria_id' => $criteriaId,
                            'initial_screening_id' => $screeningId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Study updated successfully.',
                'status' => $status,
                'criteria_count' => count($criteriaIds)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StudyController@updateScreeningStatus Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating study: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Exclusion criteria recorded for a study in one screening, as [{custom_id, description}].
     */
    private function screeningExclusionCriteria(int $studyId, string $screeningColumn, int $screeningId): array
    {
        return DB::table('study_exclusion_criteria_screening as s')
            ->join('exclusion_criterias as c', 'c.id', '=', 's.exclusion_criteria_id')
            ->where('s.study_id', $studyId)
            ->where("s.{$screeningColumn}", $screeningId)
            ->orderBy('c.id')
            ->get(['c.id', 'c.custom_id', 'c.exclusion_criteria'])
            ->map(fn ($c) => [
                'custom_id' => $c->custom_id ?: 'EC' . $c->id,
                'description' => $c->exclusion_criteria,
            ])
            ->all();
    }

    /**
     * Get all screenings for a specific study
     */
    /**
     * The Markdown version of the study's PDF, for reading the article in the study window.
     * Studies whose Markdown was not generated yet are converted now (and the result is kept).
     */
    public function markdown(Project $project, Study $study, StudyMarkdown $studyMarkdown)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        if ($study->project_id !== $project->id) {
            return response()->json(['success' => false, 'message' => 'Study not found in this project.'], 404);
        }

        if (!$study->pdf_path) {
            return response()->json(['success' => false, 'message' => 'No PDF available for this study.'], 404);
        }

        try {
            $generatedNow = !($study->md_path && Storage::disk()->exists($study->md_path));
            $markdown = $generatedNow ? $studyMarkdown->generate($study) : Storage::disk()->get($study->md_path);
        } catch (\Throwable $e) {
            Log::warning('StudyController@markdown: Markdown unavailable', [
                'study_id' => $study->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not convert this PDF to Markdown: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'markdown' => $markdown,
            'generated_now' => $generatedNow,
        ]);
    }

    public function getStudyScreenings(Request $request, Project $project, Study $study)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        // Ensure the study belongs to the project
        if ($study->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Study does not belong to this project.'
            ], 403);
        }

        // Determine which type of screenings to return (default to 'initial')
        $screeningType = $request->get('type', 'initial');
        
        if ($screeningType === 'final') {
            // Get all final screenings for this project
            try {
                $finalScreenings = \App\Models\FinalScreening::where('project_id', $project->id)
                    ->with(['aiModel', 'template', 'responsavel'])
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Get status for this study in each screening
                $screeningsData = $finalScreenings->map(function ($screening) use ($study) {
                    $pivot = DB::table('final_screening_study')
                        ->where('final_screening_id', $screening->id)
                        ->where('study_id', $study->id)
                        ->first();

                    $status = 'N/A';
                    if ($pivot) {
                        if ($pivot->status === 1 || $pivot->status === true) {
                            $status = 'Included';
                        } elseif ($pivot->status === 0 || $pivot->status === false) {
                            $status = 'Excluded';
                        }
                    }

                    return [
                        'id' => $screening->id,
                        'name' => $screening->nome,
                        'type' => $screening->tipo,
                        'ai_model' => $screening->aiModel ? $screening->aiModel->name : '-',
                        'template' => $screening->template ? $screening->template->name : '-',
                        'responsible' => $screening->responsavel->name,
                        'date' => $screening->data->format('Y-m-d'),
                        'status' => $status,
                        'output_ai' => $pivot ? $pivot->output_ai : null,
                        'exclusion_criteria' => $this->screeningExclusionCriteria($study->id, 'final_screening_id', $screening->id),
                    ];
                });
            } catch (\Exception $e) {
                // Table doesn't exist yet, return empty array
                $screeningsData = collect([]);
            }
        } else {
            // Get all initial screenings for this project
            $initialScreenings = \App\Models\InitialScreening::where('project_id', $project->id)
                ->with(['aiModel', 'template', 'responsavel'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get status for this study in each screening
            $screeningsData = $initialScreenings->map(function ($screening) use ($study) {
                $pivot = DB::table('initial_screening_study')
                    ->where('initial_screening_id', $screening->id)
                    ->where('study_id', $study->id)
                    ->first();

                $status = 'N/A';
                if ($pivot) {
                    if ($pivot->status === 1 || $pivot->status === true) {
                        $status = 'Included';
                    } elseif ($pivot->status === 0 || $pivot->status === false) {
                        $status = 'Excluded';
                    }
                }

                return [
                    'id' => $screening->id,
                    'name' => $screening->nome,
                    'type' => $screening->tipo,
                    'ai_model' => $screening->aiModel ? $screening->aiModel->name : '-',
                    'template' => $screening->template ? $screening->template->name : '-',
                    'responsible' => $screening->responsavel->name,
                    'date' => $screening->data->format('Y-m-d'),
                    'status' => $status,
                    'output_ai' => $pivot ? $pivot->output_ai : null,
                    'exclusion_criteria' => $this->screeningExclusionCriteria($study->id, 'initial_screening_id', $screening->id),
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => $screeningsData
        ]);
    }
}

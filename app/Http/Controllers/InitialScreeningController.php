<?php

namespace App\Http\Controllers;

use App\Models\InitialScreening;
use App\Models\Project;
use App\Models\AvailableModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InitialScreeningController extends Controller
{
    /**
     * Check if the authenticated user has access to the project (owner or collaborator)
     */
    private function hasAccess(Project $project): bool
    {
        $userId = Auth::id();
        if ($project->user_id === $userId) {
            return true;
        }
        return $project->collaborators()->where('user_id', $userId)->exists();
    }

    /**
     * Get a single initial screening (JSON for AJAX)
     */
    public function show(Project $project, InitialScreening $initialScreening)
    {
        if (!$this->hasAccess($project)) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this project.'
                ], 403);
            }
            abort(403, 'Unauthorized access to this project.');
        }

        if ($initialScreening->project_id !== $project->id) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Screening does not belong to this project.'
                ], 422);
            }
            abort(422, 'Screening does not belong to this project.');
        }

        // If it's an AJAX/JSON request, return JSON
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $initialScreening->id,
                    'nome' => $initialScreening->nome,
                    'tipo' => $initialScreening->tipo,
                    'ai_model_id' => $initialScreening->ai_model_id,
                    'template_id' => $initialScreening->template_id,
                    'responsavel_id' => $initialScreening->responsavel_id,
                    'data' => $initialScreening->data->format('Y-m-d'),
                ]
            ]);
        }

        // Otherwise, return the view
        $initialScreening->load(['aiModel', 'responsavel', 'project']);
        
        // Load studies with status_duplicados_exclusao = 1 for all types
        $studies = $project->studies()
            ->where('status_duplicados_exclusao', 1)
            ->orderBy('custom_id', 'asc')
            ->get()
            ->map(function($study) use ($initialScreening) {
                // Get criteria for this specific screening
                $inclusionCriteriaScreening = $study->inclusionCriteriasByScreening($initialScreening->id)->get();
                $exclusionCriteriaScreening = $study->exclusionCriteriasByScreening($initialScreening->id)->get();
                
                // Get status from pivot table
                $screeningPivot = DB::table('initial_screening_study')
                    ->where('study_id', $study->id)
                    ->where('initial_screening_id', $initialScreening->id)
                    ->first();
                $status = $screeningPivot ? $screeningPivot->status : null;
                
                return [
                    'id' => $study->id,
                    'custom_id' => $study->custom_id,
                    'name' => $study->name,
                    'doi' => $study->doi,
                    'link' => $study->link,
                    'abstract' => $study->abstract,
                    'status_selecao_inicial' => $status, // Use status from pivot table
                    'inclusion_criteria_initial' => $inclusionCriteriaScreening->pluck('id')->toArray(),
                    'exclusion_criteria_initial' => $exclusionCriteriaScreening->pluck('id')->toArray(),
                ];
            });
        
        return view('content.projects.initial_screening', compact('project', 'initialScreening', 'studies'));
    }

    /**
     * Get initial screenings for a project (DataTables)
     */
    public function index(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        if ($request->ajax() || $request->wantsJson()) {
            $draw = (int) $request->get('draw', 1);
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 15);
            
            $searchValue = '';
            if ($request->has('search') && is_array($request->get('search'))) {
                $searchValue = $request->get('search')['value'] ?? '';
            } elseif ($request->has('search')) {
                $searchValue = $request->get('search');
            }

            $query = InitialScreening::where('project_id', $project->id)
                ->with(['aiModel', 'responsavel', 'template']);

            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('nome', 'like', '%' . $searchValue . '%')
                      ->orWhere('tipo', 'like', '%' . $searchValue . '%');
                });
            }

            $totalRecords = InitialScreening::where('project_id', $project->id)->count();
            $filteredRecords = $query->count();

            $screenings = $query->orderBy('created_at', 'desc')
                ->skip($start)
                ->take($length)
                ->get();

            // Count pending/running queue jobs per initial screening (one query for the whole page).
            // Initial screening jobs serialize an InitialScreening ModelIdentifier: ...InitialScreening";s:2:"id";i:<N>;
            $pendingJobsByScreening = [];
            DB::table('jobs')
                ->where('payload', 'like', '%InitialScreening%')
                ->pluck('payload')
                ->each(function ($payload) use (&$pendingJobsByScreening) {
                    if (preg_match('/InitialScreening\W+s:2:\W+id\W+i:(\d+);/', $payload, $m)) {
                        $id = (int) $m[1];
                        $pendingJobsByScreening[$id] = ($pendingJobsByScreening[$id] ?? 0) + 1;
                    }
                });

            $data = $screenings->map(function ($screening) use ($project, $pendingJobsByScreening) {
                // Calculate progress: studies classified (included or excluded) / total studies
                // Only count studies with status_duplicados_exclusao = 1 (available for screening)
                $totalStudies = $project->studies()
                    ->where('status_duplicados_exclusao', 1)
                    ->count();
                
                // Get study IDs that are available for screening
                $availableStudyIds = $project->studies()
                    ->where('status_duplicados_exclusao', 1)
                    ->pluck('id')
                    ->toArray();
                
                $classifiedStudies = DB::table('initial_screening_study')
                    ->where('initial_screening_id', $screening->id)
                    ->whereIn('study_id', $availableStudyIds)
                    ->whereNotNull('status')
                    ->count();
                
                $progress = $totalStudies > 0 ? round(($classifiedStudies / $totalStudies) * 100, 1) : 0;
                
                return [
                    'id' => $screening->id,
                    'nome' => $screening->nome,
                    'tipo' => $screening->tipo,
                    'ai_model' => $screening->aiModel ? $screening->aiModel->name : '-',
                    'ai_model_id' => $screening->ai_model_id,
                    'template_id' => $screening->template_id,
                    'template' => $screening->template ? $screening->template->name : '-',
                    'responsavel' => $screening->responsavel->name,
                    'responsavel_id' => $screening->responsavel_id,
                    'data' => $screening->data->format('Y-m-d'),
                    'progress' => $progress,
                    'classified_studies' => $classifiedStudies,
                    'total_studies' => $totalStudies,
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid request'], 400);
    }

    /**
     * Store a newly created initial screening.
     */
    public function store(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'tipo' => 'required|in:Human,AI OpenRouter,AI Local',
            'ai_model_id' => 'nullable|exists:available_models,id',
            'template_id' => 'nullable|exists:templates,id',
            'responsavel_id' => 'required|exists:users,id',
            'data' => 'required|date',
        ]);

        // Validate AI model is required if tipo is AI
        if (in_array($validated['tipo'], ['AI OpenRouter', 'AI Local']) && empty($validated['ai_model_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'AI model is required for AI screenings.'
            ], 422);
        }

        // Validate template is required if tipo is AI
        if (in_array($validated['tipo'], ['AI OpenRouter', 'AI Local']) && empty($validated['template_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Template is required for AI screenings.'
            ], 422);
        }

        // Validate template belongs to authenticated user if provided
        if (!empty($validated['template_id'])) {
            $template = \App\Models\Template::find($validated['template_id']);
            if (!$template || $template->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid template selected.'
                ], 422);
            }
        }

        // Validate AI model is not required if tipo is Human
        if ($validated['tipo'] === 'Human' && !empty($validated['ai_model_id'])) {
            $validated['ai_model_id'] = null;
        }

        // Verify AI model belongs to the user or is accessible
        if (!empty($validated['ai_model_id'])) {
            $aiModel = AvailableModel::find($validated['ai_model_id']);
            if (!$aiModel || $aiModel->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid AI model selected.'
                ], 422);
            }
        }

        $validated['project_id'] = $project->id;
        $screening = InitialScreening::create($validated);

        // If screening type is AI OpenRouter or AI Local, automatically start processing
        if ($screening->tipo === 'AI OpenRouter') {
            try {
                \App\Jobs\ProcessOpenRouter::dispatch($screening);
                \Log::info('ProcessOpenRouter job auto-dispatched on screening creation', [
                    'screening_id' => $screening->id,
                    'project_id' => $project->id,
                ]);
            } catch (\Exception $e) {
                \Log::error('Error auto-dispatching ProcessOpenRouter job on screening creation', [
                    'screening_id' => $screening->id,
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue even if job dispatch fails - screening was created successfully
            }
        } elseif ($screening->tipo === 'AI Local') {
            try {
                \App\Jobs\ProcessOllama::dispatch($screening);
                \Log::info('ProcessOllama job auto-dispatched on screening creation', [
                    'screening_id' => $screening->id,
                    'project_id' => $project->id,
                ]);
            } catch (\Exception $e) {
                \Log::error('Error auto-dispatching ProcessOllama job on screening creation', [
                    'screening_id' => $screening->id,
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue even if job dispatch fails - screening was created successfully
            }
        }

        // Build success message based on screening type
        $message = 'Initial screening created successfully.';
        if ($screening->tipo === 'AI OpenRouter' || $screening->tipo === 'AI Local') {
            $message = 'Initial screening created successfully. Processing started automatically.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'screening' => $screening->load(['aiModel', 'responsavel'])
        ]);
    }

    /**
     * Update the specified initial screening.
     */
    public function update(Request $request, Project $project, InitialScreening $initialScreening)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        if ($initialScreening->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Screening does not belong to this project.'
            ], 422);
        }

        // Only allow updating the date field
        $validated = $request->validate([
            'data' => 'required|date',
        ]);

        // Update only the date field
        $initialScreening->update([
            'data' => $validated['data']
        ]);
        $initialScreening->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Initial screening updated successfully.',
            'screening' => $initialScreening->load(['aiModel', 'responsavel'])
        ]);
    }

    /**
     * Remove the specified initial screening.
     */
    public function destroy(Project $project, InitialScreening $initialScreening)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        if ($initialScreening->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Screening does not belong to this project.'
            ], 422);
        }

        $screeningId = $initialScreening->id;

        // Remove related jobs from queue before deleting the screening
        try {
            // Get all jobs from the jobs table
            $jobs = DB::table('jobs')->get();
            $deletedJobsCount = 0;

            foreach ($jobs as $job) {
                try {
                    $payload = json_decode($job->payload, true);
                    
                    // Check if this job is related to the InitialScreening being deleted
                    if (isset($payload['displayName'])) {
                        $jobClass = $payload['displayName'];
                        
                        // Check if it's a ProcessOpenRouter or ProcessOllama job
                        if (in_array($jobClass, ['App\\Jobs\\ProcessOpenRouter', 'App\\Jobs\\ProcessOpenRouterStudy', 'App\\Jobs\\ProcessOllama', 'App\\Jobs\\ProcessOllamaStudy'])) {
                            // Check if the job data contains the screening ID
                            if (isset($payload['data']['command'])) {
                                try {
                                    $command = unserialize($payload['data']['command'], ['allowed_classes' => true]);
                                    
                                    // Check if the job's InitialScreening ID matches
                                    if (is_object($command) && 
                                        isset($command->initialScreening) && 
                                        is_object($command->initialScreening) && 
                                        isset($command->initialScreening->id) && 
                                        $command->initialScreening->id == $screeningId) {
                                        
                                        // Delete this job
                                        DB::table('jobs')->where('id', $job->id)->delete();
                                        $deletedJobsCount++;
                                        
                                        \Log::info('Job removed from queue', [
                                            'job_id' => $job->id,
                                            'screening_id' => $screeningId,
                                            'job_class' => $jobClass,
                                        ]);
                                    }
                                } catch (\Exception $unserializeException) {
                                    // Try alternative method: check if payload contains screening ID as string
                                    $payloadString = $job->payload;
                                    if (strpos($payloadString, '"id":' . $screeningId) !== false || 
                                        strpos($payloadString, 'id";i:' . $screeningId) !== false ||
                                        strpos($payloadString, 'screening_id";i:' . $screeningId) !== false) {
                                        
                                        DB::table('jobs')->where('id', $job->id)->delete();
                                        $deletedJobsCount++;
                                        
                                        \Log::info('Job removed by string matching', [
                                            'job_id' => $job->id,
                                            'screening_id' => $screeningId,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Skip jobs that can't be parsed
                    \Log::warning('Error parsing job payload', [
                        'job_id' => $job->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            // Also remove failed jobs related to this screening
            $failedJobs = DB::table('failed_jobs')->get();
            $deletedFailedJobsCount = 0;

            foreach ($failedJobs as $failedJob) {
                try {
                    $payload = json_decode($failedJob->payload, true);
                    
                    if (isset($payload['displayName'])) {
                        $jobClass = $payload['displayName'];
                        
                        if (in_array($jobClass, ['App\\Jobs\\ProcessOpenRouter', 'App\\Jobs\\ProcessOpenRouterStudy', 'App\\Jobs\\ProcessOllama', 'App\\Jobs\\ProcessOllamaStudy'])) {
                            if (isset($payload['data']['command'])) {
                                try {
                                    $command = unserialize($payload['data']['command'], ['allowed_classes' => true]);
                                    
                                    if (is_object($command) && 
                                        isset($command->initialScreening) && 
                                        is_object($command->initialScreening) && 
                                        isset($command->initialScreening->id) && 
                                        $command->initialScreening->id == $screeningId) {
                                        
                                        DB::table('failed_jobs')->where('id', $failedJob->id)->delete();
                                        $deletedFailedJobsCount++;
                                        
                                        \Log::info('Failed job removed', [
                                            'job_id' => $failedJob->id,
                                            'screening_id' => $screeningId,
                                            'job_class' => $jobClass,
                                        ]);
                                    }
                                } catch (\Exception $unserializeException) {
                                    // Try alternative method: check if payload contains screening ID as string
                                    $payloadString = $failedJob->payload;
                                    if (strpos($payloadString, '"id":' . $screeningId) !== false || 
                                        strpos($payloadString, 'id";i:' . $screeningId) !== false ||
                                        strpos($payloadString, 'screening_id";i:' . $screeningId) !== false) {
                                        
                                        DB::table('failed_jobs')->where('id', $failedJob->id)->delete();
                                        $deletedFailedJobsCount++;
                                        
                                        \Log::info('Failed job removed by string matching', [
                                            'job_id' => $failedJob->id,
                                            'screening_id' => $screeningId,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error parsing failed job payload', [
                        'job_id' => $failedJob->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            if ($deletedJobsCount > 0 || $deletedFailedJobsCount > 0) {
                \Log::info('Jobs removed for deleted screening', [
                    'screening_id' => $screeningId,
                    'pending_jobs_removed' => $deletedJobsCount,
                    'failed_jobs_removed' => $deletedFailedJobsCount,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error removing jobs for deleted screening', [
                'screening_id' => $screeningId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Continue with deletion even if job removal fails
        }

        // Delete the screening
        $initialScreening->delete();

        return response()->json([
            'success' => true,
            'message' => 'Initial screening deleted successfully.'
        ]);
    }

    /**
     * Get available AI models for the current user
     */
    public function getAvailableModels(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        $tipo = $request->get('tipo');
        
        $query = AvailableModel::where('user_id', Auth::id());
        
        // Map screening tipo to available_model type
        if ($tipo === 'AI OpenRouter') {
            $query->where('type', 'openrouter');
        } elseif ($tipo === 'AI Local') {
            $query->where('type', 'local');
        } else {
            // If tipo is Human or invalid, return empty
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $models = $query->orderBy('name')->get()->map(function ($model) {
            return [
                'id' => $model->id,
                'name' => $model->name,
                'type' => $model->type,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $models
        ]);
    }

    /**
     * Get templates for the current user
     */
    public function getTemplates(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        $templates = \App\Models\Template::where('user_id', Auth::id())
            ->where('type', 'initial')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Get all users (for responsavel selection)
     */
    public function getUsers(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        // Get project owner
        $owner = $project->user;
        
        // Get collaborators (always fetch fresh)
        $collaborators = $project->collaborators()->get();
        
        // Combine owner and collaborators, ensuring owner is first
        $allUsers = collect([$owner]);
        
        if ($collaborators->isNotEmpty()) {
            $allUsers = $allUsers->merge($collaborators);
        }
        
        $users = $allUsers
            ->filter(function($user) {
                return $user !== null;
            })
            ->unique('id')
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Process studies using AI (OpenRouter or Ollama)
     */
    public function process(Request $request, Project $project, InitialScreening $initialScreening)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        if ($initialScreening->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Screening does not belong to this project.'
            ], 422);
        }

        if (!in_array($initialScreening->tipo, ['AI OpenRouter', 'AI Local'])) {
            return response()->json([
                'success' => false,
                'message' => 'This screening is not configured for AI processing.'
            ], 422);
        }

        // Dispatch appropriate job based on type
        // If queue connection is 'sync', job will run immediately
        // Otherwise, it will be queued and processed by queue worker
        $onlyPending = $request->boolean('only_pending');

        try {
            if ($initialScreening->tipo === 'AI OpenRouter') {
                \App\Jobs\ProcessOpenRouter::dispatch($initialScreening, $onlyPending);
                \Log::info('ProcessOpenRouter job dispatched', [
                    'screening_id' => $initialScreening->id,
                    'project_id' => $project->id,
                ]);
            } elseif ($initialScreening->tipo === 'AI Local') {
                \App\Jobs\ProcessOllama::dispatch($initialScreening, $onlyPending);
                \Log::info('ProcessOllama job dispatched', [
                    'screening_id' => $initialScreening->id,
                    'project_id' => $project->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $onlyPending
                    ? 'Processing started for studies without a result. They will be processed in the background.'
                    : 'Processing started. Studies will be processed in the background.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error dispatching processing job', [
                'screening_id' => $initialScreening->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error starting processing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download studies of this initial screening as XLS (id, title, status, screening metadata).
     */
    public function downloadStudiesXls(Project $project, InitialScreening $initialScreening)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }
        if ($initialScreening->project_id !== $project->id) {
            abort(422, 'Screening does not belong to this project.');
        }

        $rows = DB::table('initial_screening_study')
            ->where('initial_screening_id', $initialScreening->id)
            ->join('studies', 'studies.id', '=', 'initial_screening_study.study_id')
            ->orderBy('studies.custom_id', 'asc')
            ->select(
                'studies.id as study_id',
                'studies.custom_id',
                'studies.name as title',
                'initial_screening_study.status',
                'initial_screening_study.total_duration',
                'initial_screening_study.input_tokens',
                'initial_screening_study.output_tokens',
                'initial_screening_study.total_tokens',
                'initial_screening_study.cost',
                'initial_screening_study.input_ai',
                'initial_screening_study.output_ai'
            )
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Studies');

        $headers = [
            'Study ID',
            'Custom ID',
            'Title',
            'Status',
            'Duration (ms)',
            'Input Tokens',
            'Output Tokens',
            'Total Tokens',
            'Cost',
            'Input AI (preview)',
            'Output AI (preview)',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '70AD47']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($rows as $r) {
            $statusLabel = $r->status === null ? 'N/A' : ($r->status ? 'Included' : 'Excluded');
            $inputPreview = $r->input_ai ? mb_substr(strip_tags((string) $r->input_ai), 0, 500) : '';
            $outputPreview = $r->output_ai ? mb_substr(strip_tags((string) $r->output_ai), 0, 500) : '';
            $sheet->setCellValue('A' . $row, $r->study_id);
            $sheet->setCellValue('B' . $row, $r->custom_id ?? 'N/A');
            $sheet->setCellValue('C' . $row, $r->title ?? 'N/A');
            $sheet->setCellValue('D' . $row, $statusLabel);
            $sheet->setCellValue('E' . $row, $r->total_duration);
            $sheet->setCellValue('F' . $row, $r->input_tokens);
            $sheet->setCellValue('G' . $row, $r->output_tokens);
            $sheet->setCellValue('H' . $row, $r->total_tokens);
            $sheet->setCellValue('I' . $row, $r->cost);
            $sheet->setCellValue('J' . $row, $inputPreview);
            $sheet->setCellValue('K' . $row, $outputPreview);
            $row++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('J')->setWidth(40);
        $sheet->getColumnDimension('K')->setWidth(40);
        $sheet->getStyle('J2:K' . ($row - 1))->getAlignment()->setWrapText(true);

        $filename = 'initial_screening_' . $initialScreening->id . '_studies_' . date('Y-m-d_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

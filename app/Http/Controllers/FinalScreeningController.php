<?php

namespace App\Http\Controllers;

use App\Models\FinalScreening;
use App\Models\Project;
use App\Models\AvailableModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinalScreeningController extends Controller
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
     * Get a single final screening (JSON for AJAX)
     */
    public function show(Project $project, FinalScreening $finalScreening)
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

        if ($finalScreening->project_id !== $project->id) {
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
                    'id' => $finalScreening->id,
                    'nome' => $finalScreening->nome,
                    'tipo' => $finalScreening->tipo,
                    'ai_model_id' => $finalScreening->ai_model_id,
                    'template_id' => $finalScreening->template_id,
                    'temperature' => $finalScreening->temperature,
                    'responsavel_id' => $finalScreening->responsavel_id,
                    'data' => $finalScreening->data->format('Y-m-d'),
                ]
            ]);
        }

        // Otherwise, return the view
        $finalScreening->load(['aiModel', 'responsavel', 'project']);

        // Load studies with status_selecao_inicial = 1 (included in initial selection)
        $studies = $project->studies()
            ->where('status_selecao_inicial', 1)
            ->orderBy('custom_id', 'asc')
            ->get()
            ->map(function($study) use ($finalScreening) {
                // Get criteria for this specific screening
                $inclusionCriteriaScreening = $study->inclusionCriteriasByFinalScreening($finalScreening->id)->get();
                $exclusionCriteriaScreening = $study->exclusionCriteriasByFinalScreening($finalScreening->id)->get();

                // Get status from pivot table
                $screeningPivot = DB::table('final_screening_study')
                    ->where('study_id', $study->id)
                    ->where('final_screening_id', $finalScreening->id)
                    ->first();
                $status = $screeningPivot ? $screeningPivot->status : null;

                return [
                    'id' => $study->id,
                    'custom_id' => $study->custom_id,
                    'name' => $study->name,
                    'doi' => $study->doi,
                    'link' => $study->link,
                    'abstract' => $study->abstract,
                    'status_selecao_final' => $status, // Use status from pivot table
                    'inclusion_criteria_final' => $inclusionCriteriaScreening->pluck('id')->toArray(),
                    'exclusion_criteria_final' => $exclusionCriteriaScreening->pluck('id')->toArray(),
                ];
            });

        // Decision distribution for the summary chart (status: 1 = include, 0 = exclude, null = N/A)
        $statusSummary = [
            'include' => $studies->filter(fn ($s) => $s['status_selecao_final'] !== null && (int) $s['status_selecao_final'] === 1)->count(),
            'exclude' => $studies->filter(fn ($s) => $s['status_selecao_final'] !== null && (int) $s['status_selecao_final'] === 0)->count(),
            'na' => $studies->filter(fn ($s) => $s['status_selecao_final'] === null)->count(),
        ];

        return view('content.projects.final_screening', compact('project', 'finalScreening', 'studies', 'statusSummary'));
    }

    /**
     * Get final screenings for a project (DataTables)
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

            $query = FinalScreening::where('project_id', $project->id)
                ->with(['aiModel', 'responsavel', 'template']);

            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('nome', 'like', '%' . $searchValue . '%')
                      ->orWhere('tipo', 'like', '%' . $searchValue . '%');
                });
            }

            $totalRecords = FinalScreening::where('project_id', $project->id)->count();
            $filteredRecords = $query->count();

            $screenings = $query->orderBy('created_at', 'desc')
                ->skip($start)
                ->take($length)
                ->get();

            // Count pending/running queue jobs per final screening (one query for the whole page).
            // Final screening jobs serialize a FinalScreening ModelIdentifier: ...FinalScreening";s:2:"id";i:<N>;
            $pendingJobsByScreening = [];
            DB::table('jobs')
                ->where('payload', 'like', '%FinalScreening%')
                ->pluck('payload')
                ->each(function ($payload) use (&$pendingJobsByScreening) {
                    if (preg_match('/FinalScreening\W+s:2:\W+id\W+i:(\d+);/', $payload, $m)) {
                        $id = (int) $m[1];
                        $pendingJobsByScreening[$id] = ($pendingJobsByScreening[$id] ?? 0) + 1;
                    }
                });

            $data = $screenings->map(function ($screening) use ($project, $pendingJobsByScreening) {
                // Calculate progress: studies classified (included or excluded) / total studies
                // Only count studies with status_selecao_inicial = 1 (available for final screening)
                $totalStudies = $project->studies()
                    ->where('status_selecao_inicial', 1)
                    ->count();

                // Get study IDs that are available for screening
                $availableStudyIds = $project->studies()
                    ->where('status_selecao_inicial', 1)
                    ->pluck('id')
                    ->toArray();

                $classifiedStudies = DB::table('final_screening_study')
                    ->where('final_screening_id', $screening->id)
                    ->whereIn('study_id', $availableStudyIds)
                    ->whereNotNull('status')
                    ->count();

                $progress = $totalStudies > 0 ? round(($classifiedStudies / $totalStudies) * 100, 1) : 0;

                // processing: jobs still queued/running; completed: every study has a response;
                // incomplete: nothing in the queue but some studies have no response
                $pendingJobs = $pendingJobsByScreening[$screening->id] ?? 0;
                if ($pendingJobs > 0) {
                    $processingStatus = 'processing';
                } elseif ($totalStudies > 0 && $classifiedStudies >= $totalStudies) {
                    $processingStatus = 'completed';
                } else {
                    $processingStatus = 'incomplete';
                }

                return [
                    'id' => $screening->id,
                    'nome' => $screening->nome,
                    'tipo' => $screening->tipo,
                    'ai_model' => $screening->aiModel ? $screening->aiModel->name : '-',
                    'ai_model_id' => $screening->ai_model_id,
                    'template_id' => $screening->template_id,
                    'temperature' => $screening->temperature,
                    'template' => $screening->template ? $screening->template->name : '-',
                    'responsavel' => $screening->responsavel->name,
                    'responsavel_id' => $screening->responsavel_id,
                    'data' => $screening->data->format('Y-m-d'),
                    'progress' => $progress,
                    'classified_studies' => $classifiedStudies,
                    'total_studies' => $totalStudies,
                    'processing_status' => $processingStatus,
                    'pending_jobs' => $pendingJobs,
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
     * Store a newly created final screening.
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
            'temperature' => 'nullable|numeric|min:0|max:2',
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

        $validated['temperature'] = $validated['tipo'] === 'Human' ? null : ($validated['temperature'] ?? 0.1);
        $validated['project_id'] = $project->id;
        $screening = FinalScreening::create($validated);

        // If screening type is AI OpenRouter or AI Local, automatically start processing
        if ($screening->tipo === 'AI OpenRouter') {
            try {
                \App\Jobs\ProcessFinalOpenRouter::dispatch($screening);
                \Log::info('ProcessFinalOpenRouter job auto-dispatched on screening creation', [
                    'screening_id' => $screening->id,
                    'project_id' => $project->id,
                ]);
            } catch (\Exception $e) {
                \Log::error('Error auto-dispatching ProcessFinalOpenRouter job on screening creation', [
                    'screening_id' => $screening->id,
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue even if job dispatch fails - screening was created successfully
            }
        } elseif ($screening->tipo === 'AI Local') {
            try {
                \App\Jobs\ProcessFinalOllama::dispatch($screening);
                \Log::info('ProcessFinalOllama job auto-dispatched on screening creation', [
                    'screening_id' => $screening->id,
                    'project_id' => $project->id,
                ]);
            } catch (\Exception $e) {
                \Log::error('Error auto-dispatching ProcessFinalOllama job on screening creation', [
                    'screening_id' => $screening->id,
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue even if job dispatch fails - screening was created successfully
            }
        }

        // Build success message based on screening type
        $message = 'Final screening created successfully.';
        if ($screening->tipo === 'AI OpenRouter' || $screening->tipo === 'AI Local') {
            $message = 'Final screening created successfully. Processing started automatically.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'screening' => $screening->load(['aiModel', 'responsavel'])
        ]);
    }

    /**
     * Update the specified final screening.
     */
    public function update(Request $request, Project $project, FinalScreening $finalScreening)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        if ($finalScreening->project_id !== $project->id) {
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
        $finalScreening->update([
            'data' => $validated['data']
        ]);
        $finalScreening->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Final screening updated successfully.',
            'screening' => $finalScreening->load(['aiModel', 'responsavel'])
        ]);
    }

    /**
     * Remove the specified final screening.
     */
    public function destroy(Project $project, FinalScreening $finalScreening)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        if ($finalScreening->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Screening does not belong to this project.'
            ], 422);
        }

        $screeningId = $finalScreening->id;

        // Remove related jobs from queue before deleting the screening
        try {
            // Get all jobs from the jobs table
            $jobs = DB::table('jobs')->get();
            $deletedJobsCount = 0;

            foreach ($jobs as $job) {
                try {
                    $payload = json_decode($job->payload, true);

                    // Check if this job is related to the FinalScreening being deleted
                    if (isset($payload['displayName'])) {
                        $jobClass = $payload['displayName'];

                        // Check if it's a ProcessFinalOpenRouter or ProcessFinalOllama job
                        if (in_array($jobClass, ['App\\Jobs\\ProcessFinalOpenRouter', 'App\\Jobs\\ProcessFinalOpenRouterStudy', 'App\\Jobs\\ProcessFinalOllama', 'App\\Jobs\\ProcessFinalOllamaStudy'])) {
                            // Check if the job data contains the screening ID
                            if (isset($payload['data']['command'])) {
                                try {
                                    $command = unserialize($payload['data']['command'], ['allowed_classes' => true]);

                                    // Check if the job's FinalScreening ID matches
                                    if (is_object($command) &&
                                        isset($command->finalScreening) &&
                                        is_object($command->finalScreening) &&
                                        isset($command->finalScreening->id) &&
                                        $command->finalScreening->id == $screeningId) {

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

                        if (in_array($jobClass, ['App\\Jobs\\ProcessFinalOpenRouter', 'App\\Jobs\\ProcessFinalOpenRouterStudy', 'App\\Jobs\\ProcessFinalOllama', 'App\\Jobs\\ProcessFinalOllamaStudy'])) {
                            if (isset($payload['data']['command'])) {
                                try {
                                    $command = unserialize($payload['data']['command'], ['allowed_classes' => true]);

                                    if (is_object($command) &&
                                        isset($command->finalScreening) &&
                                        is_object($command->finalScreening) &&
                                        isset($command->finalScreening->id) &&
                                        $command->finalScreening->id == $screeningId) {

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
        $finalScreening->delete();

        return response()->json([
            'success' => true,
            'message' => 'Final screening deleted successfully.'
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
            ->where('type', 'final')
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
    public function process(Project $project, FinalScreening $finalScreening)
    {
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        if ($finalScreening->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Screening does not belong to this project.'
            ], 422);
        }

        if (!in_array($finalScreening->tipo, ['AI OpenRouter', 'AI Local'])) {
            return response()->json([
                'success' => false,
                'message' => 'This screening is not configured for AI processing.'
            ], 422);
        }

        // Dispatch appropriate job based on type
        // If queue connection is 'sync', job will run immediately
        // Otherwise, it will be queued and processed by queue worker
        try {
            // Get count of studies that will be processed
            $studiesCount = $finalScreening->pendingStudies()->count();

            \Log::info('Final Screening Process - Starting', [
                'screening_id' => $finalScreening->id,
                'project_id' => $project->id,
                'tipo' => $finalScreening->tipo,
                'studies_count' => $studiesCount,
            ]);

            if ($finalScreening->tipo === 'AI OpenRouter') {
                \App\Jobs\ProcessFinalOpenRouter::dispatch($finalScreening);
                \Log::info('ProcessFinalOpenRouter job dispatched', [
                    'screening_id' => $finalScreening->id,
                    'project_id' => $project->id,
                    'studies_count' => $studiesCount,
                ]);
            } elseif ($finalScreening->tipo === 'AI Local') {
                \App\Jobs\ProcessFinalOllama::dispatch($finalScreening);
                \Log::info('ProcessFinalOllama job dispatched', [
                    'screening_id' => $finalScreening->id,
                    'project_id' => $project->id,
                    'studies_count' => $studiesCount,
                ]);
            } else {
                \Log::warning('Final Screening Process - Invalid tipo', [
                    'screening_id' => $finalScreening->id,
                    'tipo' => $finalScreening->tipo,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Processing started for {$studiesCount} studies without a result. They will be processed in the background."
            ]);
        } catch (\Exception $e) {
            \Log::error('Error dispatching processing job', [
                'screening_id' => $finalScreening->id,
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
     * Download studies of this final screening as XLS (id, title, status, screening metadata).
     */
    public function downloadStudiesXls(Project $project, FinalScreening $finalScreening)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }
        if ($finalScreening->project_id !== $project->id) {
            abort(422, 'Screening does not belong to this project.');
        }

        $rows = DB::table('final_screening_study')
            ->where('final_screening_id', $finalScreening->id)
            ->join('studies', 'studies.id', '=', 'final_screening_study.study_id')
            ->orderBy('studies.custom_id', 'asc')
            ->select(
                'studies.id as study_id',
                'studies.custom_id',
                'studies.name as title',
                'final_screening_study.status',
                'final_screening_study.total_duration',
                'final_screening_study.input_tokens',
                'final_screening_study.output_tokens',
                'final_screening_study.total_tokens',
                'final_screening_study.cost',
                'final_screening_study.input_ai',
                'final_screening_study.output_ai'
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

        $filename = 'final_screening_' . $finalScreening->id . '_studies_' . date('Y-m-d_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

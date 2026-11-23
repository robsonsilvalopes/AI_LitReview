<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExtractionOllama;
use App\Jobs\ProcessExtractionOpenRouter;
use App\Models\AvailableModel;
use App\Models\Extraction;
use App\Models\Project;
use App\Models\Study;
use App\Models\Template;
use App\Models\ExtractionData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExtractionController extends Controller
{
    private function hasAccess(Project $project): bool
    {
        $userId = Auth::id();
        if ($project->user_id === $userId) {
            return true;
        }

        return $project->collaborators()->where('user_id', $userId)->exists();
    }

    public function index(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
        }

        if (!($request->ajax() || $request->wantsJson())) {
            return response()->json(['success' => false, 'message' => 'Invalid request'], 400);
        }

        $draw = (int) $request->get('draw', 1);
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 15);

        $searchValue = '';
        if ($request->has('search') && is_array($request->get('search'))) {
            $searchValue = $request->get('search')['value'] ?? '';
        } elseif ($request->has('search')) {
            $searchValue = (string) $request->get('search');
        }

        $query = Extraction::where('project_id', $project->id)->with(['aiModel', 'responsavel', 'template']);

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('nome', 'like', '%' . $searchValue . '%')
                    ->orWhere('tipo', 'like', '%' . $searchValue . '%');
            });
        }

        $totalRecords = Extraction::where('project_id', $project->id)->count();
        $filteredRecords = $query->count();

        $extractions = $query->orderBy('created_at', 'desc')->skip($start)->take($length)->get();

        $pendingJobsByExtraction = $this->queuedJobsByExtraction();

        $data = $extractions->map(function (Extraction $extraction) use ($project, $pendingJobsByExtraction) {
            $totalStudies = $project->studies()->where('status_selecao_final', 1)->count();
            $availableStudyIds = $project->studies()->where('status_selecao_final', 1)->pluck('id')->toArray();

            if ($extraction->tipo === 'Human') {
                // For Human extractions, count studies that have at least one extraction_data saved
                $result = DB::table('study_extraction_data')
                    ->where('extraction_id', $extraction->id)
                    ->whereIn('study_id', $availableStudyIds)
                    ->selectRaw('COUNT(DISTINCT study_id) as count')
                    ->first();
                $processedStudies = $result ? (int) $result->count : 0;
            } else {
                // For AI extractions, count studies with output_ai
                $processedStudies = DB::table('extraction_study')
                    ->where('extraction_id', $extraction->id)
                    ->whereIn('study_id', $availableStudyIds)
                    ->whereNotNull('output_ai')
                    ->count();
            }

            $progress = $totalStudies > 0 ? round(($processedStudies / $totalStudies) * 100, 1) : 0;

            // processing: jobs still queued/running; completed: every study has a result;
            // incomplete: nothing in the queue but some studies have no result
            $pendingJobs = $pendingJobsByExtraction[$extraction->id] ?? 0;
            if ($pendingJobs > 0) {
                $processingStatus = 'processing';
            } elseif ($totalStudies > 0 && $processedStudies >= $totalStudies) {
                $processingStatus = 'completed';
            } else {
                $processingStatus = 'incomplete';
            }

            return [
                'id' => $extraction->id,
                'nome' => $extraction->nome,
                'tipo' => $extraction->tipo,
                'ai_model' => $extraction->aiModel ? $extraction->aiModel->name : '-',
                'template' => $extraction->template ? $extraction->template->name : '-',
                'temperature' => $extraction->temperature,
                'responsavel' => $extraction->responsavel ? $extraction->responsavel->name : '-',
                'data' => optional($extraction->data)->format('Y-m-d'),
                'progress' => $progress,
                'processed_studies' => $processedStudies,
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

    public function show(Request $request, Project $project, Extraction $extraction)
    {
        if (!$this->hasAccess($project)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
            }
            abort(403, 'Unauthorized access to this project.');
        }

        if ($extraction->project_id !== $project->id) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Extraction does not belong to this project.'], 422);
            }
            abort(422, 'Extraction does not belong to this project.');
        }

        // JSON for edit modal / API usage
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $extraction->id,
                    'nome' => $extraction->nome,
                    'tipo' => $extraction->tipo,
                    'ai_model_id' => $extraction->ai_model_id,
                    'template_id' => $extraction->template_id,
                    'temperature' => $extraction->temperature,
                    'responsavel_id' => $extraction->responsavel_id,
                    'data' => optional($extraction->data)->format('Y-m-d'),
                ],
            ]);
        }

        // View: show extraction results (for both Human and AI)
        $extraction->load(['responsavel', 'project', 'aiModel', 'template']);

        $studies = $project->studies()
            ->where('status_selecao_final', 1)
            ->orderBy('custom_id', 'asc')
            ->get()
            ->map(function (Study $study) use ($project, $extraction) {
                // Load extraction data values for this specific extraction
                $extractionDataValues = DB::table('study_extraction_data')
                    ->where('study_id', $study->id)
                    ->where('extraction_id', $extraction->id)
                    ->whereIn('extraction_data_id', function ($query) use ($project) {
                        $query->select('id')
                            ->from('extraction_data')
                            ->where('project_id', $project->id);
                    })
                    ->get()
                    ->mapWithKeys(function ($item) {
                        // Ensure keys are integers to match extraction_data.id
                        return [(int)$item->extraction_data_id => $item->value];
                    })
                    ->toArray();

                // Load AI input and output from extraction_study pivot table
                $extractionStudy = DB::table('extraction_study')
                    ->where('extraction_id', $extraction->id)
                    ->where('study_id', $study->id)
                    ->first();

                return [
                    'id' => $study->id,
                    'custom_id' => $study->custom_id,
                    'name' => $study->name,
                    'doi' => $study->doi,
                    'link' => $study->link,
                    'abstract' => $study->abstract,
                    'pdf_path' => $study->pdf_path,
                    'extraction_data_values' => $extractionDataValues,
                    'input_ai' => $extractionStudy->input_ai ?? null,
                    'output_ai' => $extractionStudy->output_ai ?? null,
                    // Passages the AI quoted per field: {extraction_data_id: [quote, ...]}
                    'evidence' => !empty($extractionStudy->evidence) ? json_decode($extractionStudy->evidence, true) : null,
                ];
            });

        $extractionData = $project->extractionData()->orderBy('custom_id')->get();

        // Same rule as the extraction list's progress: AI = output saved, Human = extraction form saved
        $studyIds = $studies->pluck('id');
        $extractedCount = $extraction->tipo === 'Human'
            ? DB::table('study_extraction_data')
                ->where('extraction_id', $extraction->id)
                ->whereIn('study_id', $studyIds)
                ->distinct()
                ->count('study_id')
            : DB::table('extraction_study')
                ->where('extraction_id', $extraction->id)
                ->whereIn('study_id', $studyIds)
                ->whereNotNull('output_ai')
                ->distinct()
                ->count('study_id');
        $statusItems = [
            ['label' => 'Extracted', 'count' => $extractedCount, 'color' => 'success', 'icon' => 'ri-checkbox-circle-line'],
            ['label' => 'Not extracted', 'count' => $studies->count() - $extractedCount, 'color' => 'secondary', 'icon' => 'ri-checkbox-blank-circle-line'],
        ];

        return view('content.projects.extraction_human', compact('project', 'extraction', 'studies', 'extractionData', 'statusItems'));
    }

    public function store(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
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

        if (in_array($validated['tipo'], ['AI OpenRouter', 'AI Local'], true) && empty($validated['ai_model_id'])) {
            return response()->json(['success' => false, 'message' => 'AI model is required for AI extractions.'], 422);
        }

        if (in_array($validated['tipo'], ['AI OpenRouter', 'AI Local'], true) && empty($validated['template_id'])) {
            return response()->json(['success' => false, 'message' => 'Template is required for AI extractions.'], 422);
        }

        if (!empty($validated['template_id'])) {
            $template = Template::find($validated['template_id']);
            if (!$template || $template->project_id !== $project->id || $template->type !== 'extraction') {
                return response()->json(['success' => false, 'message' => 'Invalid extraction template selected.'], 422);
            }
        }

        if (!empty($validated['ai_model_id'])) {
            $aiModel = AvailableModel::find($validated['ai_model_id']);
            if (!$aiModel || $aiModel->user_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Invalid AI model selected.'], 422);
            }
        }

        $validated['temperature'] = $validated['tipo'] === 'Human' ? null : ($validated['temperature'] ?? 0.1);
        $validated['project_id'] = $project->id;
        $extraction = Extraction::create($validated);

        if ($extraction->tipo === 'AI OpenRouter') {
            ProcessExtractionOpenRouter::dispatch($extraction);
        } elseif ($extraction->tipo === 'AI Local') {
            ProcessExtractionOllama::dispatch($extraction);
        }

        $response = [
            'success' => true,
            'message' => ($extraction->tipo === 'Human')
                ? 'Extraction created successfully.'
                : 'Extraction created successfully. Processing started automatically.',
            'extraction' => [
                'id' => $extraction->id,
                'tipo' => $extraction->tipo,
            ],
        ];

        // For Human type, include the URL to redirect to the extraction view
        if ($extraction->tipo === 'Human') {
            $response['redirect_url'] = route('extractions.show', ['project' => $project->id, 'extraction' => $extraction->id]);
        }

        return response()->json($response);
    }

    public function getAvailableModels(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
        }

        $tipo = $request->get('tipo');

        $query = AvailableModel::where('user_id', Auth::id());
        if ($tipo === 'AI OpenRouter') {
            $query->where('type', 'openrouter');
        } elseif ($tipo === 'AI Local') {
            $query->where('type', 'local');
        } else {
            return response()->json(['success' => true, 'data' => []]);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function getTemplates(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
        }

        // Only the project's own templates
        $templates = $project->templates()
            ->where('type', 'extraction')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function getUsers(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
        }

        $owner = $project->user;
        $collaborators = $project->collaborators()->get();

        $users = collect([$owner])
            ->merge($collaborators)
            ->filter()
            ->unique('id')
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->values();

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * Count queued/running jobs per extraction id (one query).
     * Extraction jobs serialize an Extraction ModelIdentifier: ...Models\Extraction";s:2:"id";i:<N>;
     */
    private function queuedJobsByExtraction(): array
    {
        $counts = [];
        DB::table('jobs')
            ->where('payload', 'like', '%ProcessExtraction%')
            ->pluck('payload')
            ->each(function ($payload) use (&$counts) {
                if (preg_match('/Models\W+Extraction\W+s:2:\W+id\W+i:(\d+);/', $payload, $m)) {
                    $id = (int) $m[1];
                    $counts[$id] = ($counts[$id] ?? 0) + 1;
                }
            });

        return $counts;
    }

    public function process(Request $request, Project $project, Extraction $extraction)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
        }

        if ($extraction->project_id !== $project->id) {
            return response()->json(['success' => false, 'message' => 'Extraction does not belong to this project.'], 422);
        }

        if (!in_array($extraction->tipo, ['AI OpenRouter', 'AI Local'], true)) {
            return response()->json(['success' => false, 'message' => 'This extraction is not configured for AI processing.'], 422);
        }

        $pendingOnly = $request->boolean('pending_only');

        if ($pendingOnly) {
            // Queuing again while jobs are still waiting would process the same studies twice
            $queuedJobs = $this->queuedJobsByExtraction()[$extraction->id] ?? 0;
            if ($queuedJobs > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "This extraction is still processing ({$queuedJobs} jobs in the queue). Wait for it to finish before continuing.",
                ], 409);
            }

            $studiesCount = $extraction->pendingStudies()->count();
            if ($studiesCount === 0) {
                return response()->json(['success' => false, 'message' => 'Every study in this extraction already has a result.'], 422);
            }
        }

        if ($extraction->tipo === 'AI OpenRouter') {
            ProcessExtractionOpenRouter::dispatch($extraction, $pendingOnly);
        } else {
            ProcessExtractionOllama::dispatch($extraction, $pendingOnly);
        }

        return response()->json([
            'success' => true,
            'message' => $pendingOnly
                ? "Processing started for {$studiesCount} studies without a result. They will be processed in the background."
                : 'Processing started.',
        ]);
    }

    public function saveStudyExtractionData(Request $request, Project $project, Extraction $extraction, Study $study)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
        }

        if ($extraction->project_id !== $project->id) {
            return response()->json(['success' => false, 'message' => 'Extraction does not belong to this project.'], 422);
        }

        if ($study->project_id !== $project->id) {
            return response()->json(['success' => false, 'message' => 'Study does not belong to this project.'], 422);
        }

        $validated = $request->validate([
            'extraction_data' => 'required|array',
            'extraction_data.*.extraction_data_id' => 'required|exists:extraction_data,id',
            'extraction_data.*.value' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['extraction_data'] as $data) {
                $extractionDataId = $data['extraction_data_id'];
                $value = $data['value'] ?? null;

                // Verify that extraction_data belongs to the project
                $extractionData = DB::table('extraction_data')
                    ->where('id', $extractionDataId)
                    ->where('project_id', $project->id)
                    ->first();

                if (!$extractionData) {
                    throw new \Exception("Extraction data ID {$extractionDataId} does not belong to this project.");
                }

                // Check if record exists
                $existing = DB::table('study_extraction_data')
                    ->where('study_id', $study->id)
                    ->where('extraction_data_id', $extractionDataId)
                    ->where('extraction_id', $extraction->id)
                    ->first();

                if ($existing) {
                    // Update existing record
                    DB::table('study_extraction_data')
                        ->where('study_id', $study->id)
                        ->where('extraction_data_id', $extractionDataId)
                        ->where('extraction_id', $extraction->id)
                        ->update([
                            'value' => $value,
                            'updated_at' => now(),
                        ]);
                } else {
                    // Insert new record
                    DB::table('study_extraction_data')
                        ->insert([
                            'study_id' => $study->id,
                            'extraction_data_id' => $extractionDataId,
                            'extraction_id' => $extraction->id,
                            'value' => $value,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Extraction data saved successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error saving extraction data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download extraction data as Excel file.
     */
    public function downloadExtractionData(Request $request, Project $project, Extraction $extraction)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        if ($extraction->project_id !== $project->id) {
            abort(422, 'Extraction does not belong to this project.');
        }

        // Get all extraction data fields for this project
        $extractionDataFields = ExtractionData::where('project_id', $project->id)
            ->orderBy('custom_id', 'asc')
            ->get(['id', 'custom_id', 'name']);

        // Get all studies with status_selecao_final = 1
        $studies = Study::where('project_id', $project->id)
            ->where('status_selecao_final', 1)
            ->orderBy('custom_id', 'asc')
            ->get(['id', 'custom_id', 'name']);

        // Get all extraction data values for this extraction
        $extractionDataValues = DB::table('study_extraction_data')
            ->where('extraction_id', $extraction->id)
            ->get()
            ->groupBy('study_id')
            ->map(function ($group) {
                return $group->keyBy('extraction_data_id');
            });

        // Create spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Extraction Data');

        // Set headers: Study ID, Study Name, and all extraction data fields
        $headers = ['Study ID', 'Study Name'];
        foreach ($extractionDataFields as $field) {
            $headers[] = $field->name . ' (' . $field->custom_id . ')';
        }
        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);

        // Add data
        $row = 2;
        foreach ($studies as $study) {
            $sheet->setCellValue('A' . $row, $study->custom_id ?? 'N/A');
            $sheet->setCellValue('B' . $row, $study->name ?? 'N/A');

            $col = 3; // Start from column C (after Study ID and Study Name)
            foreach ($extractionDataFields as $field) {
                $value = $extractionDataValues->get($study->id)?->get($field->id)?->value ?? '';
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                $sheet->setCellValue($cell, $value);
                $col++;
            }
            $row++;
        }

        // Auto-size columns (iterate by index to support columns beyond Z in PHP 8.4+)
        $numColumns = count($headers);
        for ($colIndex = 1; $colIndex <= $numColumns; $colIndex++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set Study Name column to a reasonable width
        $sheet->getColumnDimension('B')->setAutoSize(false);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getStyle('B2:B' . ($row - 1))->getAlignment()->setWrapText(true);

        // Create the response
        $filename = 'extraction_data_' . $extraction->id . '_' . date('Y-m-d_His') . '.xlsx';
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Save to temp file and return as download
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function destroy(Project $project, Extraction $extraction)
    {
        if (!$this->hasAccess($project)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this project.'], 403);
        }

        if ($extraction->project_id !== $project->id) {
            return response()->json(['success' => false, 'message' => 'Extraction does not belong to this project.'], 422);
        }

        $extraction->delete();

        return response()->json(['success' => true, 'message' => 'Extraction deleted successfully.']);
    }
}

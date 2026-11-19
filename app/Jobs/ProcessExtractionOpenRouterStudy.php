<?php

namespace App\Jobs;

use App\Models\Extraction;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use App\Services\ExtractionEvidence;
use App\Services\StudyMarkdown;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessExtractionOpenRouterStudy implements ShouldQueue
{
    use Queueable;

    protected $extraction;
    protected $study;

    /**
     * Create a new job instance.
     */
    public function __construct(Extraction $extraction, Study $study)
    {
        $this->extraction = $extraction;
        $this->study = $study;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->extraction->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->extraction->tipo !== 'AI OpenRouter') {
            Log::warning('ProcessExtractionOpenRouterStudy called for non-AI OpenRouter extraction', [
                'extraction_id' => $this->extraction->id,
                'tipo' => $this->extraction->tipo
            ]);
            return;
        }

        if (!$this->extraction->template) {
            Log::error('ProcessExtractionOpenRouterStudy: Template not found', [
                'extraction_id' => $this->extraction->id
            ]);
            return;
        }

        if (!$this->extraction->aiModel) {
            Log::error('ProcessExtractionOpenRouterStudy: AI Model not found', [
                'extraction_id' => $this->extraction->id
            ]);
            return;
        }

        $user = $this->extraction->responsavel;
        if (!$user || !$user->openrouter_api_key) {
            Log::error('ProcessExtractionOpenRouterStudy: OpenRouter API key not found', [
                'extraction_id' => $this->extraction->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $project = $this->extraction->project;
        $extractionData = $project->extractionData()->orderBy('custom_id')->get();

        // Answers plus "_evidence": the passages of the study each answer was taken from
        $evidenceService = app(ExtractionEvidence::class);
        $jsondataReplacement = $evidenceService->jsonData($extractionData);

        $modelId = $this->extraction->aiModel->model_id;
        $study = $this->study;
        $prompt = null;

        try {
            $hasPdf = false;
            $fullStudyContent = '';

            // Full text: the Markdown generated from the PDF on upload (pymupdf4llm)
            $fullText = app(StudyMarkdown::class)->fullTextFor($study);
            $hasPdf = $fullText['has_text'];
            $fullStudyContent = $fullText['content'];

            if (!$hasPdf) {
                Log::info('ProcessExtractionOpenRouterStudy: Study has no PDF, skipping AI processing', [
                    'extraction_id' => $this->extraction->id,
                    'study_id' => $study->id,
                ]);

                DB::table('extraction_study')
                    ->updateOrInsert(
                        [
                            'extraction_id' => $this->extraction->id,
                            'study_id' => $study->id,
                        ],
                        [
                            'input_ai' => null,
                            'output_ai' => null,
                            'evidence' => null,
                            'total_duration' => null,
                            'input_tokens' => null,
                            'output_tokens' => null,
                            'total_tokens' => null,
                            'cost' => 0.0,
                            'updated_at' => now(),
                            'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        ]
                    );

                return;
            }

            $prompt = $this->extraction->template->template;

            $prompt = str_ireplace('[[id]]', $study->custom_id ?? $study->id, $prompt);
            $prompt = str_ireplace('[[title]]', $study->name ?? '', $prompt);
            $prompt = str_ireplace('[[fullstudy]]', $fullStudyContent, $prompt);

            $prompt = str_ireplace('[[jsondata]]', $jsondataReplacement, $prompt);

            $requestStartTime = microtime(true);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $user->openrouter_api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'AILitReview Extraction',
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $modelId,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                // Extractions created before this setting existed keep the previous 0.7
                'temperature' => $this->extraction->temperature ?? 0.7,
            ]);
            $requestEndTime = microtime(true);
            $duration = (int)(($requestEndTime - $requestStartTime) * 1000);

            $responseBody = $response->body();
            $responseData = $response->json();
            $statusCode = $response->status();

            $inputTokens = 0;
            $outputTokens = 0;
            $tokens = 0;
            $cost = 0.0;

            if (isset($responseData['usage'])) {
                $usage = $responseData['usage'];
                $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
                $outputTokens = (int) ($usage['completion_tokens'] ?? 0);
                $tokens = (int) ($usage['total_tokens'] ?? ($inputTokens + $outputTokens));
                $cost = (float) ($usage['cost'] ?? 0.0);
            }

            $outputJson = null;
            if ($response->successful() && isset($responseData['choices'][0]['message']['content'])) {
                // The answers hold a nested "_evidence" object, so take the whole outer {...}
                $outputJson = $evidenceService->parseJson($responseData['choices'][0]['message']['content']);
            }
            $evidence = $evidenceService->fromOutput($outputJson, $extractionData);

            $inputAi = $prompt;
            $outputAi = $responseBody;

            DB::table('extraction_study')
                ->updateOrInsert(
                    [
                        'extraction_id' => $this->extraction->id,
                        'study_id' => $study->id,
                    ],
                    [
                        'input_ai' => $inputAi,
                        'output_ai' => $outputAi,
                        'evidence' => $evidence ? json_encode($evidence, JSON_UNESCAPED_UNICODE) : null,
                        'total_duration' => $duration,
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'total_tokens' => $tokens,
                        'cost' => $cost,
                        'updated_at' => now(),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    ]
                );

            if ($outputJson && is_array($outputJson)) {
                foreach ($extractionData as $data) {
                    // By field name, or by field ID ("ED1") when the model followed the template's example
                    $value = $evidenceService->answerFor($outputJson, $data);
                    if ($value !== null) {
                        DB::table('study_extraction_data')
                            ->updateOrInsert(
                                [
                                    'study_id' => $study->id,
                                    'extraction_data_id' => $data->id,
                                    'extraction_id' => $this->extraction->id,
                                ],
                                [
                                    'value' => is_array($value) ? json_encode($value) : (string)$value,
                                    'updated_at' => now(),
                                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                                ]
                            );
                    }
                }
            }

            Log::info('ProcessExtractionOpenRouterStudy: Study processed and saved', [
                'extraction_id' => $this->extraction->id,
                'study_id' => $study->id,
                'has_json' => $outputJson !== null,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessExtractionOpenRouterStudy: Error processing study', [
                'extraction_id' => $this->extraction->id,
                'study_id' => $study->id,
                'error' => $e->getMessage(),
            ]);

            DB::table('extraction_study')
                ->updateOrInsert(
                    [
                        'extraction_id' => $this->extraction->id,
                        'study_id' => $study->id,
                    ],
                    [
                        'input_ai' => $prompt ?? null,
                        'output_ai' => json_encode(['error' => $e->getMessage()]),
                        'updated_at' => now(),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    ]
                );
        }
    }
}

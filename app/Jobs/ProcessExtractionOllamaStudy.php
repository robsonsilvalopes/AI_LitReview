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

class ProcessExtractionOllamaStudy implements ShouldQueue
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

        if ($this->extraction->tipo !== 'AI Local') {
            Log::warning('ProcessExtractionOllamaStudy called for non-AI Local extraction', [
                'extraction_id' => $this->extraction->id,
                'tipo' => $this->extraction->tipo
            ]);
            return;
        }

        if (!$this->extraction->template) {
            Log::error('ProcessExtractionOllamaStudy: Template not found', [
                'extraction_id' => $this->extraction->id
            ]);
            return;
        }

        if (!$this->extraction->aiModel) {
            Log::error('ProcessExtractionOllamaStudy: AI Model not found', [
                'extraction_id' => $this->extraction->id
            ]);
            return;
        }

        $user = $this->extraction->responsavel;
        if (!$user || !$user->ollama_url) {
            Log::error('ProcessExtractionOllamaStudy: Ollama URL not found', [
                'extraction_id' => $this->extraction->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $ollamaUrl = $user->ollama_url;
        if (!str_ends_with($ollamaUrl, '/')) {
            $ollamaUrl .= '/';
        }

        $urlsToTry = [$ollamaUrl];
        if (str_contains($ollamaUrl, 'localhost') || str_contains($ollamaUrl, '127.0.0.1')) {
            $dockerUrl = str_replace(['localhost', '127.0.0.1'], 'host.docker.internal', $ollamaUrl);
            $urlsToTry[] = $dockerUrl;
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
                Log::info('ProcessExtractionOllamaStudy: Study has no PDF, skipping AI processing', [
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

            $responseBody = null;
            $responseData = null;
            $lastError = null;
            $successfulUrl = null;
            $inputTokens = 0;
            $outputTokens = 0;
            $tokens = 0;
            $duration = 0;

            $requestStartTime = microtime(true);
            foreach ($urlsToTry as $url) {
                try {
                    $response = Http::timeout(300)
                        ->connectTimeout(10)
                        ->post($url . 'api/chat', [
                            'model' => $modelId,
                            'messages' => [
                                [
                                    'role' => 'user',
                                    'content' => $prompt
                                ]
                            ],
                            'stream' => false,
                            'options' => [
                                'num_ctx' => 20000,
                                // Extractions created before this setting existed keep the model's default
                                ...($this->extraction->temperature !== null
                                    ? ['temperature' => $this->extraction->temperature]
                                    : []),
                            ],
                        ]);
                    $requestEndTime = microtime(true);
                    $duration = (int)(($requestEndTime - $requestStartTime) * 1000);

                    if ($response->successful()) {
                        $responseBody = $response->body();
                        $responseData = $response->json();
                        $successfulUrl = $url;

                        if (isset($responseData['prompt_eval_count'])) {
                            $inputTokens = (int) $responseData['prompt_eval_count'];
                        }
                        if (isset($responseData['eval_count'])) {
                            $outputTokens = (int) $responseData['eval_count'];
                        }
                        if (isset($responseData['prompt_eval_count']) && isset($responseData['eval_count'])) {
                            $tokens = $inputTokens + $outputTokens;
                        }

                        break;
                    } else {
                        $lastError = new \Exception('HTTP ' . $response->status() . ': ' . $response->body());
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    $lastError = $e;
                    continue;
                } catch (\Exception $e) {
                    $lastError = $e;
                    continue;
                }
            }

            if (!$responseBody && $responseData) {
                $responseBody = json_encode($responseData);
            }

            $outputJson = null;
            if ($responseData) {
                // Comments are stripped only outside strings: the quotes in "_evidence" may hold URLs
                $outputJson = $evidenceService->parseJson($responseData['message']['content'] ?? null);
            }
            $evidence = $evidenceService->fromOutput($outputJson, $extractionData);

            $inputAi = $prompt;
            $outputAi = $responseBody ?? json_encode(['error' => 'Unable to connect to Ollama']);

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
                        'cost' => 0.0,
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

            Log::info('ProcessExtractionOllamaStudy: Study processed and saved', [
                'extraction_id' => $this->extraction->id,
                'study_id' => $study->id,
                'has_json' => $outputJson !== null,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessExtractionOllamaStudy: Error processing study', [
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

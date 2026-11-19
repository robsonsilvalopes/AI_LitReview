<?php

namespace App\Jobs;

use App\Models\FinalScreening;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use App\Services\StudyMarkdown;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessFinalOllamaStudy implements ShouldQueue
{
    use Queueable;

    protected $finalScreening;
    protected $study;

    /**
     * Create a new job instance.
     */
    public function __construct(FinalScreening $finalScreening, Study $study)
    {
        $this->finalScreening = $finalScreening;
        $this->study = $study;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // A queued duplicate must not process (or overwrite) a study that already has a decision
        $alreadyDecided = DB::table('final_screening_study')
            ->where('final_screening_id', $this->finalScreening->id)
            ->where('study_id', $this->study->id)
            ->whereNotNull('status')
            ->exists();
        if ($alreadyDecided) {
            return;
        }

        $this->finalScreening->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->finalScreening->tipo !== 'AI Local') {
            Log::warning('ProcessFinalOllamaStudy called for non-AI Local screening', [
                'screening_id' => $this->finalScreening->id,
                'tipo' => $this->finalScreening->tipo
            ]);
            return;
        }

        if (!$this->finalScreening->template) {
            Log::error('ProcessFinalOllamaStudy: Template not found', [
                'screening_id' => $this->finalScreening->id
            ]);
            return;
        }

        if (!$this->finalScreening->aiModel) {
            Log::error('ProcessFinalOllamaStudy: AI Model not found', [
                'screening_id' => $this->finalScreening->id
            ]);
            return;
        }

        $user = $this->finalScreening->responsavel;
        if (!$user || !$user->ollama_url) {
            Log::error('ProcessFinalOllamaStudy: Ollama URL not found', [
                'screening_id' => $this->finalScreening->id,
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

        $project = $this->finalScreening->project;
        $inclusionCriterias = $project->inclusionCriterias()->orderBy('id')->get();
        $exclusionCriterias = $project->exclusionCriterias()->orderBy('id')->get();

        $inclusionCriteriaText = $inclusionCriterias->map(function ($criteria) {
            $customId = $criteria->custom_id ?? 'IC' . $criteria->id;
            $criteriaText = $criteria->inclusion_criteria ?? '';
            return $customId . ' - ' . $criteriaText;
        })->filter(function ($line) {
            return !empty(trim($line));
        })->join("\n");

        $exclusionCriteriaText = $exclusionCriterias->map(function ($criteria) {
            $customId = $criteria->custom_id ?? 'EC' . $criteria->id;
            $criteriaText = $criteria->exclusion_criteria ?? '';
            return $customId . ' - ' . $criteriaText;
        })->filter(function ($line) {
            return !empty(trim($line));
        })->join("\n");

        $modelId = $this->finalScreening->aiModel->model_id;
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
                // Full text not available yet: leave pending, this is not an exclusion by the criteria
                Log::info('ProcessFinalOllamaStudy: Study has no readable PDF, leaving pending without AI processing', [
                    'screening_id' => $this->finalScreening->id,
                    'study_id' => $study->id,
                ]);

                DB::table('final_screening_study')
                    ->updateOrInsert(
                        [
                            'final_screening_id' => $this->finalScreening->id,
                            'study_id' => $study->id,
                        ],
                        [
                            'status' => null,
                            'input_ai' => null,
                            'output_ai' => null,
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

            $prompt = $this->finalScreening->template->template;

            $prompt = str_ireplace('[[id]]', $study->custom_id ?? $study->id, $prompt);
            $prompt = str_ireplace('[[title]]', $study->name ?? '', $prompt);
            $prompt = str_ireplace('[[fullstudy]]', $fullStudyContent, $prompt);
            $prompt = str_ireplace('[[abstract]]', '', $prompt);
            $prompt = str_ireplace('[[inclusioncriteria]]', $inclusionCriteriaText, $prompt);
            $prompt = str_ireplace('[[exclusioncriteria]]', $exclusionCriteriaText, $prompt);

            $responseBody = null;
            $responseData = null;
            $lastError = null;
            $successfulUrl = null;

            $requestStartTime = microtime(true);
            foreach ($urlsToTry as $url) {
                try {
                    Log::info('ProcessFinalOllamaStudy: Calling Ollama API', [
                        'screening_id' => $this->finalScreening->id,
                        'study_id' => $study->id,
                        'url' => $url . 'api/chat',
                        'model' => $modelId,
                    ]);

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
                                'num_ctx' => 32768,
                                'temperature' => $this->finalScreening->temperature ?? 0.1,
                            ],
                        ]);
                    $requestEndTime = microtime(true);
                    $duration = (int)(($requestEndTime - $requestStartTime) * 1000);

                    if ($response->successful()) {
                        $responseBody = $response->body();
                        $responseData = $response->json();
                        $successfulUrl = $url;

                        $inputTokens = 0;
                        $outputTokens = 0;
                        $tokens = 0;

                        if (isset($responseData['prompt_eval_count'])) {
                            $inputTokens = (int) $responseData['prompt_eval_count'];
                        }
                        if (isset($responseData['eval_count'])) {
                            $outputTokens = (int) $responseData['eval_count'];
                        }
                        if (isset($responseData['prompt_eval_count']) && isset($responseData['eval_count'])) {
                            $tokens = $inputTokens + $outputTokens;
                        }

                        Log::info('ProcessFinalOllamaStudy: Full API Response', [
                            'screening_id' => $this->finalScreening->id,
                            'study_id' => $study->id,
                            'url' => $url,
                            'duration_ms' => $duration,
                            'input_tokens' => $inputTokens,
                            'output_tokens' => $outputTokens,
                            'total_tokens' => $tokens,
                            'response_body' => $responseBody,
                            'response_data' => $responseData,
                            'has_message' => isset($responseData['message']),
                            'message_content' => $responseData['message']['content'] ?? null,
                        ]);
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

            $status = null;
            $outputJson = null;

            if ($responseData) {
                $content = $responseData['message']['content'] ?? null;

                if ($content) {
                    Log::info('ProcessFinalOllamaStudy: Content extracted for status parsing', [
                        'screening_id' => $this->finalScreening->id,
                        'study_id' => $study->id,
                        'content_length' => strlen($content),
                        'content_full' => $content,
                    ]);

                    if (preg_match('/"status"\s*:\s*(\d+)/s', $content, $statusMatches)) {
                        $statusValue = (int) $statusMatches[1];
                        $status = (bool) $statusValue;

                        Log::info('ProcessFinalOllamaStudy: Status extracted via direct regex', [
                            'screening_id' => $this->finalScreening->id,
                            'study_id' => $study->id,
                            'status_value' => $statusValue,
                            'status_parsed' => $status,
                            'match' => $statusMatches[0],
                        ]);
                    } else {
                        $jsonMatch = null;

                        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $content, $codeBlockMatches)) {
                            $jsonMatch = $codeBlockMatches[1];
                            Log::info('ProcessFinalOllamaStudy: JSON found in code block', [
                                'screening_id' => $this->finalScreening->id,
                                'study_id' => $study->id,
                                'json_preview' => substr($jsonMatch, 0, 200),
                            ]);
                        } elseif (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
                            $jsonMatch = $matches[0];
                            Log::info('ProcessFinalOllamaStudy: JSON found in plain text', [
                                'screening_id' => $this->finalScreening->id,
                                'study_id' => $study->id,
                                'json_preview' => substr($jsonMatch, 0, 200),
                            ]);
                        }

                        if ($jsonMatch) {
                            $cleanedJson = preg_replace('/\/\/.*$/m', '', $jsonMatch);
                            $cleanedJson = preg_replace('/\/\*[\s\S]*?\*\//', '', $cleanedJson);
                            $cleanedJson = trim($cleanedJson);

                            Log::info('ProcessFinalOllamaStudy: Attempting JSON parse', [
                                'screening_id' => $this->finalScreening->id,
                                'study_id' => $study->id,
                                'cleaned_json_preview' => substr($cleanedJson, 0, 200),
                            ]);

                            $outputJson = json_decode($cleanedJson, true);
                            // Formato do artigo/templates Likert: "overall": "include" | "exclude"
                            if (json_last_error() === JSON_ERROR_NONE && !isset($outputJson['status']) && isset($outputJson['overall'])) {
                                $outputJson['status'] = $outputJson['overall'];
                            }
                            if (json_last_error() === JSON_ERROR_NONE && isset($outputJson['status'])) {
                                $statusValue = $outputJson['status'];
                                if (is_bool($statusValue)) {
                                    $status = $statusValue;
                                } elseif (is_string($statusValue)) {
                                    $status = strtolower($statusValue) === 'true' || strtolower($statusValue) === '1' || strtolower($statusValue) === 'include';
                                } elseif (is_numeric($statusValue)) {
                                    $status = (bool) $statusValue;
                                }

                                Log::info('ProcessFinalOllamaStudy: Status extracted from parsed JSON', [
                                    'screening_id' => $this->finalScreening->id,
                                    'study_id' => $study->id,
                                    'status_value' => $statusValue,
                                    'status_parsed' => $status,
                                ]);
                            } else {
                                Log::warning('ProcessFinalOllamaStudy: JSON parse failed or no status field', [
                                    'screening_id' => $this->finalScreening->id,
                                    'study_id' => $study->id,
                                    'json_error' => json_last_error_msg(),
                                    'cleaned_json_preview' => substr($cleanedJson, 0, 200),
                                ]);
                            }
                        } else {
                            Log::warning('ProcessFinalOllamaStudy: No JSON pattern found in content', [
                                'screening_id' => $this->finalScreening->id,
                                'study_id' => $study->id,
                                'content_preview' => substr($content, 0, 300),
                            ]);
                        }
                    }
                } else {
                    Log::warning('ProcessFinalOllamaStudy: No content in response', [
                        'screening_id' => $this->finalScreening->id,
                        'study_id' => $study->id,
                        'response_data_keys' => array_keys($responseData ?? []),
                    ]);
                }
            } else {
                if (!$responseBody) {
                    $responseBody = json_encode([
                        'error' => 'Unable to connect to Ollama',
                        'url' => $ollamaUrl,
                        'last_error' => $lastError ? $lastError->getMessage() : 'Unknown error'
                    ]);
                }

                Log::warning('ProcessFinalOllamaStudy: API Response not successful', [
                    'screening_id' => $this->finalScreening->id,
                    'study_id' => $study->id,
                    'tried_urls' => $urlsToTry,
                    'last_error' => $lastError ? $lastError->getMessage() : 'Unknown error',
                ]);
            }

            $inputAi = $prompt;
            $outputAi = $responseBody ?? json_encode($responseData ?? []);

            $studyDuration = isset($duration) ? $duration : null;
            $studyInputTokens = isset($inputTokens) ? $inputTokens : null;
            $studyOutputTokens = isset($outputTokens) ? $outputTokens : null;
            $studyTotalTokens = isset($tokens) ? $tokens : null;
            $studyCost = 0.0;

            Log::info('ProcessFinalOllamaStudy: Saving to database', [
                'screening_id' => $this->finalScreening->id,
                'study_id' => $study->id,
                'status_to_save' => $status,
                'status_type' => gettype($status),
                'status_is_null' => is_null($status),
                'duration' => $studyDuration,
                'input_tokens' => $studyInputTokens,
                'output_tokens' => $studyOutputTokens,
                'total_tokens' => $studyTotalTokens,
                'cost' => $studyCost,
            ]);

            DB::table('final_screening_study')
                ->updateOrInsert(
                    [
                        'final_screening_id' => $this->finalScreening->id,
                        'study_id' => $study->id,
                    ],
                    [
                        'status' => $status,
                        'input_ai' => $inputAi,
                        'output_ai' => $outputAi,
                        'total_duration' => $studyDuration,
                        'input_tokens' => $studyInputTokens,
                        'output_tokens' => $studyOutputTokens,
                        'total_tokens' => $studyTotalTokens,
                        'cost' => $studyCost,
                        'updated_at' => now(),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    ]
                );

            // Record the criteria the model scored as clearly applying (same tables as human screening)
            app(\App\Services\AiScreeningCriteria::class)
                ->syncStudy($this->finalScreening, $study->id, $status, $outputAi);

            $saved = DB::table('final_screening_study')
                ->where('final_screening_id', $this->finalScreening->id)
                ->where('study_id', $study->id)
                ->first();

            Log::info('ProcessFinalOllamaStudy: Verification after save', [
                'screening_id' => $this->finalScreening->id,
                'study_id' => $study->id,
                'status_saved' => $saved->status ?? 'not found',
                'status_saved_type' => gettype($saved->status ?? null),
            ]);

            Log::info('ProcessFinalOllamaStudy: Study processed', [
                'screening_id' => $this->finalScreening->id,
                'study_id' => $study->id,
                'status' => $status,
                'url_used' => $successfulUrl ?? $ollamaUrl
            ]);

        } catch (\Exception $e) {
            $errorPrompt = $prompt ?? null;

            Log::error('ProcessFinalOllamaStudy: Error processing study', [
                'screening_id' => $this->finalScreening->id,
                'study_id' => $study->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            DB::table('final_screening_study')
                ->updateOrInsert(
                    [
                        'final_screening_id' => $this->finalScreening->id,
                        'study_id' => $study->id,
                    ],
                    [
                        'status' => null,
                        'input_ai' => $errorPrompt,
                        'output_ai' => json_encode(['error' => $e->getMessage()]),
                        'updated_at' => now(),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    ]
                );
        }
    }
}

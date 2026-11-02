<?php

namespace App\Jobs;

use App\Models\InitialScreening;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessOllamaStudy implements ShouldQueue
{
    use Queueable;

    protected $initialScreening;
    protected $study;

    /**
     * Create a new job instance.
     */
    public function __construct(InitialScreening $initialScreening, Study $study)
    {
        $this->initialScreening = $initialScreening;
        $this->study = $study;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load relationships
        $this->initialScreening->load(['project', 'template', 'aiModel', 'responsavel']);

        // Verify screening type
        if ($this->initialScreening->tipo !== 'AI Local') {
            Log::warning('ProcessOllamaStudy called for non-AI Local screening', [
                'screening_id' => $this->initialScreening->id,
                'tipo' => $this->initialScreening->tipo
            ]);
            return;
        }

        // Verify required data
        if (!$this->initialScreening->template) {
            Log::error('ProcessOllamaStudy: Template not found', [
                'screening_id' => $this->initialScreening->id
            ]);
            return;
        }

        if (!$this->initialScreening->aiModel) {
            Log::error('ProcessOllamaStudy: AI Model not found', [
                'screening_id' => $this->initialScreening->id
            ]);
            return;
        }

        // Get Ollama URL from responsible user
        $user = $this->initialScreening->responsavel;
        if (!$user || !$user->ollama_url) {
            Log::error('ProcessOllamaStudy: Ollama URL not found', [
                'screening_id' => $this->initialScreening->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $ollamaUrl = $user->ollama_url;

        // Ensure URL ends with /
        if (!str_ends_with($ollamaUrl, '/')) {
            $ollamaUrl .= '/';
        }

        // If URL contains localhost and we're in Docker, try host.docker.internal as fallback
        $urlsToTry = [$ollamaUrl];
        if (str_contains($ollamaUrl, 'localhost') || str_contains($ollamaUrl, '127.0.0.1')) {
            $dockerUrl = str_replace(['localhost', '127.0.0.1'], 'host.docker.internal', $ollamaUrl);
            $urlsToTry[] = $dockerUrl;
        }

        // Get project inclusion and exclusion criteria
        $project = $this->initialScreening->project;
        $inclusionCriterias = $project->inclusionCriterias()->orderBy('id')->get();
        $exclusionCriterias = $project->exclusionCriterias()->orderBy('id')->get();

        // Build criteria strings
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

        $modelId = $this->initialScreening->aiModel->model_id;
        $study = $this->study;

        $prompt = null;
        try {
            // Build prompt from template
            $prompt = $this->initialScreening->template->template;

            // Replace variables (case-insensitive to handle variations)
            $prompt = str_ireplace('[[id]]', $study->custom_id ?? $study->id, $prompt);
            $prompt = str_ireplace('[[title]]', $study->name ?? '', $prompt);
            $prompt = str_ireplace('[[abstract]]', $study->abstract ?? '', $prompt);
            $prompt = str_ireplace('[[inclusioncriteria]]', $inclusionCriteriaText, $prompt);
            $prompt = str_ireplace('[[exclusioncriteria]]', $exclusionCriteriaText, $prompt);

            // Call Ollama API using the configured URL, with fallback for Docker
            $responseBody = null;
            $responseData = null;
            $lastError = null;
            $successfulUrl = null;

            $requestStartTime = microtime(true);
            foreach ($urlsToTry as $url) {
                try {
                    Log::info('ProcessOllamaStudy: Calling Ollama API', [
                        'screening_id' => $this->initialScreening->id,
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
                            // Screenings created before this setting existed keep the model's default
                            ...($this->initialScreening->temperature !== null
                                ? ['options' => ['temperature' => $this->initialScreening->temperature]]
                                : []),
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

                        Log::info('ProcessOllamaStudy: Full API Response', [
                            'screening_id' => $this->initialScreening->id,
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
                    Log::info('ProcessOllamaStudy: Content extracted for status parsing', [
                        'screening_id' => $this->initialScreening->id,
                        'study_id' => $study->id,
                        'content_length' => strlen($content),
                        'content_full' => $content,
                    ]);

                    if (preg_match('/"status"\s*:\s*(\d+)/s', $content, $statusMatches)) {
                        $statusValue = (int) $statusMatches[1];
                        $status = (bool) $statusValue;

                        Log::info('ProcessOllamaStudy: Status extracted via direct regex', [
                            'screening_id' => $this->initialScreening->id,
                            'study_id' => $study->id,
                            'status_value' => $statusValue,
                            'status_parsed' => $status,
                            'match' => $statusMatches[0],
                        ]);
                    } else {
                        $jsonMatch = null;

                        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $content, $codeBlockMatches)) {
                            $jsonMatch = $codeBlockMatches[1];
                            Log::info('ProcessOllamaStudy: JSON found in code block', [
                                'screening_id' => $this->initialScreening->id,
                                'study_id' => $study->id,
                                'json_preview' => substr($jsonMatch, 0, 200),
                            ]);
                        } elseif (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
                            $jsonMatch = $matches[0];
                            Log::info('ProcessOllamaStudy: JSON found in plain text', [
                                'screening_id' => $this->initialScreening->id,
                                'study_id' => $study->id,
                                'json_preview' => substr($jsonMatch, 0, 200),
                            ]);
                        }

                        if ($jsonMatch) {
                            $cleanedJson = preg_replace('/\/\/.*$/m', '', $jsonMatch);
                            $cleanedJson = preg_replace('/\/\*[\s\S]*?\*\//', '', $cleanedJson);
                            $cleanedJson = trim($cleanedJson);

                            Log::info('ProcessOllamaStudy: Attempting JSON parse', [
                                'screening_id' => $this->initialScreening->id,
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

                                Log::info('ProcessOllamaStudy: Status extracted from parsed JSON', [
                                    'screening_id' => $this->initialScreening->id,
                                    'study_id' => $study->id,
                                    'status_value' => $statusValue,
                                    'status_parsed' => $status,
                                ]);
                            } else {
                                Log::warning('ProcessOllamaStudy: JSON parse failed or no status field', [
                                    'screening_id' => $this->initialScreening->id,
                                    'study_id' => $study->id,
                                    'json_error' => json_last_error_msg(),
                                    'cleaned_json_preview' => substr($cleanedJson, 0, 200),
                                ]);
                            }
                        } else {
                            Log::warning('ProcessOllamaStudy: No JSON pattern found in content', [
                                'screening_id' => $this->initialScreening->id,
                                'study_id' => $study->id,
                                'content_preview' => substr($content, 0, 300),
                            ]);
                        }
                    }
                } else {
                    Log::warning('ProcessOllamaStudy: No content in response', [
                        'screening_id' => $this->initialScreening->id,
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

                Log::warning('ProcessOllamaStudy: API Response not successful', [
                    'screening_id' => $this->initialScreening->id,
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

            Log::info('ProcessOllamaStudy: Saving to database', [
                'screening_id' => $this->initialScreening->id,
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

            DB::table('initial_screening_study')
                ->updateOrInsert(
                    [
                        'initial_screening_id' => $this->initialScreening->id,
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
                ->syncStudy($this->initialScreening, $study->id, $status, $outputAi);

            $saved = DB::table('initial_screening_study')
                ->where('initial_screening_id', $this->initialScreening->id)
                ->where('study_id', $study->id)
                ->first();

            Log::info('ProcessOllamaStudy: Verification after save', [
                'screening_id' => $this->initialScreening->id,
                'study_id' => $study->id,
                'status_saved' => $saved->status ?? 'not found',
                'status_saved_type' => gettype($saved->status ?? null),
            ]);

            Log::info('ProcessOllamaStudy: Study processed', [
                'screening_id' => $this->initialScreening->id,
                'study_id' => $study->id,
                'status' => $status,
                'url_used' => $successfulUrl ?? $ollamaUrl
            ]);

        } catch (\Exception $e) {
            $errorPrompt = $prompt ?? null;

            Log::error('ProcessOllamaStudy: Error processing study', [
                'screening_id' => $this->initialScreening->id,
                'study_id' => $study->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            DB::table('initial_screening_study')
                ->updateOrInsert(
                    [
                        'initial_screening_id' => $this->initialScreening->id,
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

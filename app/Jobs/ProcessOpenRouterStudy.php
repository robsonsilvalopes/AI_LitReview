<?php

namespace App\Jobs;

use App\Models\InitialScreening;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessOpenRouterStudy implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

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
        $this->initialScreening->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->initialScreening->tipo !== 'AI OpenRouter') {
            Log::warning('ProcessOpenRouterStudy called for non-AI OpenRouter screening', [
                'screening_id' => $this->initialScreening->id,
                'tipo' => $this->initialScreening->tipo
            ]);
            return;
        }

        if (!$this->initialScreening->template) {
            Log::error('ProcessOpenRouterStudy: Template not found', [
                'screening_id' => $this->initialScreening->id
            ]);
            return;
        }

        if (!$this->initialScreening->aiModel) {
            Log::error('ProcessOpenRouterStudy: AI Model not found', [
                'screening_id' => $this->initialScreening->id
            ]);
            return;
        }

        $user = $this->initialScreening->responsavel;
        if (!$user || !$user->openrouter_api_key) {
            Log::error('ProcessOpenRouterStudy: OpenRouter API key not found', [
                'screening_id' => $this->initialScreening->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $project = $this->initialScreening->project;
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

        Log::info('ProcessOpenRouterStudy: Criteria built', [
            'screening_id' => $this->initialScreening->id,
            'study_id' => $this->study->id,
            'inclusion_count' => $inclusionCriterias->count(),
            'exclusion_count' => $exclusionCriterias->count(),
            'inclusion_text_preview' => substr($inclusionCriteriaText, 0, 200),
            'exclusion_text_preview' => substr($exclusionCriteriaText, 0, 200),
        ]);

        $study = $this->study;
        $prompt = null;

        try {
            $prompt = $this->initialScreening->template->template;

            $prompt = str_ireplace('[[id]]', $study->custom_id ?? $study->id, $prompt);
            // Before the study text, so a [[pico]] inside an article is left as it is
            $prompt = str_ireplace('[[pico]]', app(\App\Services\QuestionFramework::class)->text($project), $prompt);
            $prompt = str_ireplace('[[title]]', $study->name ?? '', $prompt);
            $prompt = str_ireplace('[[abstract]]', $study->abstract ?? '', $prompt);
            $prompt = str_ireplace('[[inclusioncriteria]]', $inclusionCriteriaText, $prompt);
            $prompt = str_ireplace('[[exclusioncriteria]]', $exclusionCriteriaText, $prompt);

            Log::info('ProcessOpenRouterStudy: Prompt built', [
                'screening_id' => $this->initialScreening->id,
                'study_id' => $study->id,
                'prompt_preview' => substr($prompt, 0, 500),
                'inclusion_criteria_in_prompt' => str_contains(strtolower($prompt), strtolower($inclusionCriteriaText)),
                'exclusion_criteria_in_prompt' => str_contains(strtolower($prompt), strtolower($exclusionCriteriaText)),
            ]);

            $modelId = $this->initialScreening->aiModel->model_id;

            $requestStartTime = microtime(true);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $user->openrouter_api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'AILitReview Screening',
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $modelId,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                // Screenings created before this setting existed keep their original value
                'temperature' => $this->initialScreening->temperature ?? 0.7,
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

            Log::info('ProcessOpenRouterStudy: API Response', [
                'screening_id' => $this->initialScreening->id,
                'study_id' => $study->id,
                'status_code' => $statusCode,
                'successful' => $response->successful(),
                'response_preview' => substr($responseBody, 0, 500),
                'has_choices' => isset($responseData['choices']),
            ]);

            $status = null;
            $outputJson = null;

            if ($response->successful() && isset($responseData['choices'][0]['message']['content'])) {
                $content = $responseData['choices'][0]['message']['content'];

                $jsonMatch = null;
                if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $matches)) {
                    $jsonMatch = $matches[0];
                }

                if ($jsonMatch) {
                    $outputJson = json_decode($jsonMatch, true);
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

                        Log::info('ProcessOpenRouterStudy: Status extracted from JSON', [
                            'screening_id' => $this->initialScreening->id,
                            'study_id' => $study->id,
                            'status_value' => $statusValue,
                            'status_parsed' => $status,
                            'json_match' => $jsonMatch,
                        ]);
                    } else {
                        Log::warning('ProcessOpenRouterStudy: JSON found but no status field', [
                            'screening_id' => $this->initialScreening->id,
                            'study_id' => $study->id,
                            'json_match' => $jsonMatch,
                            'json_error' => json_last_error_msg(),
                        ]);
                    }
                } else {
                    Log::warning('ProcessOpenRouterStudy: No JSON found in response content', [
                        'screening_id' => $this->initialScreening->id,
                        'study_id' => $study->id,
                        'content_preview' => substr($content, 0, 200),
                    ]);
                }
            } else {
                Log::warning('ProcessOpenRouterStudy: API Response not successful', [
                    'screening_id' => $this->initialScreening->id,
                    'study_id' => $study->id,
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'response_data' => $responseData,
                ]);
            }

            $inputAi = $prompt;
            $outputAi = $responseBody;

            $studyDuration = isset($duration) ? $duration : null;
            $studyInputTokens = isset($inputTokens) ? $inputTokens : null;
            $studyOutputTokens = isset($outputTokens) ? $outputTokens : null;
            $studyTotalTokens = isset($tokens) ? $tokens : null;
            $studyCost = isset($cost) ? $cost : 0.0;

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

            Log::info('ProcessOpenRouterStudy: Study processed', [
                'screening_id' => $this->initialScreening->id,
                'study_id' => $study->id,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            $errorPrompt = $prompt ?? 'No AI input available';

            Log::error('ProcessOpenRouterStudy: Error processing study', [
                'screening_id' => $this->initialScreening->id,
                'study_id' => $study->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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
                        'output_ai' => json_encode(['error' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 1000)]),
                        'updated_at' => now(),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    ]
                );
        }
    }
}

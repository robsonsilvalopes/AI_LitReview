<?php

namespace App\Jobs;

use App\Models\FinalScreening;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessFinalOpenRouterStudy implements ShouldQueue
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
        $this->finalScreening->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->finalScreening->tipo !== 'AI OpenRouter') {
            Log::warning('ProcessFinalOpenRouterStudy called for non-AI OpenRouter screening', [
                'screening_id' => $this->finalScreening->id,
                'tipo' => $this->finalScreening->tipo
            ]);
            return;
        }

        if (!$this->finalScreening->template) {
            Log::error('ProcessFinalOpenRouterStudy: Template not found', [
                'screening_id' => $this->finalScreening->id
            ]);
            return;
        }

        if (!$this->finalScreening->aiModel) {
            Log::error('ProcessFinalOpenRouterStudy: AI Model not found', [
                'screening_id' => $this->finalScreening->id
            ]);
            return;
        }

        $user = $this->finalScreening->responsavel;
        if (!$user || !$user->openrouter_api_key) {
            Log::error('ProcessFinalOpenRouterStudy: OpenRouter API key not found', [
                'screening_id' => $this->finalScreening->id,
                'user_id' => $user?->id
            ]);
            return;
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

        Log::info('ProcessFinalOpenRouterStudy: Criteria built', [
            'screening_id' => $this->finalScreening->id,
            'study_id' => $this->study->id,
            'inclusion_count' => $inclusionCriterias->count(),
            'exclusion_count' => $exclusionCriterias->count(),
            'inclusion_text_preview' => substr($inclusionCriteriaText, 0, 200),
            'exclusion_text_preview' => substr($exclusionCriteriaText, 0, 200),
        ]);

        $study = $this->study;
        $prompt = null;

        try {
            $hasPdf = false;
            $fullStudyContent = '';

            if ($study->pdf_path) {
                try {
                    if (Storage::disk('s3')->exists($study->pdf_path)) {
                        $pdfContent = Storage::disk('s3')->get($study->pdf_path);
                        $tempPath = sys_get_temp_dir() . '/' . uniqid('pdf_') . '.pdf';
                        file_put_contents($tempPath, $pdfContent);

                        if (file_exists($tempPath) && filesize($tempPath) > 0) {
                            $output = [];
                            $returnVar = 0;
                            exec("pdftotext -blocks -nopgbrk '" . escapeshellarg($tempPath) . "' - 2>&1", $output, $returnVar);
                            $fullStudyContent = implode("\n", $output);
                            @unlink($tempPath);

                            if (stripos($fullStudyContent, 'Usage:') !== false ||
                                stripos($fullStudyContent, 'pdftotext version') !== false ||
                                stripos($fullStudyContent, 'Copyright') !== false) {
                                $fullStudyContent = '';
                            }

                            if (!empty(trim($fullStudyContent)) && $returnVar === 0) {
                                $hasPdf = true;
                            } else {
                                $pdfContent = Storage::disk('s3')->get($study->pdf_path);
                                $tempPath2 = sys_get_temp_dir() . '/' . uniqid('pdf_') . '.pdf';
                                file_put_contents($tempPath2, $pdfContent);

                                if (file_exists($tempPath2) && filesize($tempPath2) > 0) {
                                    $output2 = [];
                                    $returnVar2 = 0;
                                    exec("pdftotext '" . escapeshellarg($tempPath2) . "' - 2>&1", $output2, $returnVar2);
                                    $fullStudyContent = implode("\n", $output2);
                                    @unlink($tempPath2);

                                    if (stripos($fullStudyContent, 'Usage:') !== false ||
                                        stripos($fullStudyContent, 'pdftotext version') !== false ||
                                        stripos($fullStudyContent, 'Copyright') !== false) {
                                        $fullStudyContent = '';
                                    }

                                    if (!empty(trim($fullStudyContent)) && $returnVar2 === 0) {
                                        $hasPdf = true;
                                    } else {
                                        Log::warning('ProcessFinalOpenRouterStudy: PDF text extraction returned empty or failed', [
                                            'screening_id' => $this->finalScreening->id,
                                            'study_id' => $study->id,
                                            'pdf_path' => $study->pdf_path,
                                            'temp_file_size' => filesize($tempPath2) ?? 'unknown',
                                            'return_code' => $returnVar2,
                                            'pdftotext_output_preview' => substr($fullStudyContent, 0, 200),
                                        ]);
                                        $fullStudyContent = 'PDF file available but text extraction failed. The PDF may be image-based, password-protected, or corrupted.';
                                    }
                                } else {
                                    @unlink($tempPath2);
                                    $fullStudyContent = 'PDF file available but text extraction failed. Temporary file could not be created.';
                                }
                            }
                        } else {
                            @unlink($tempPath);
                            $fullStudyContent = 'PDF file available but text extraction failed. Temporary file could not be created.';
                        }
                    } else {
                        Log::warning('ProcessFinalOpenRouterStudy: PDF file not found in storage', [
                            'screening_id' => $this->finalScreening->id,
                            'study_id' => $study->id,
                            'pdf_path' => $study->pdf_path,
                        ]);
                        $fullStudyContent = 'PDF file path exists but file not found in storage.';
                    }
                } catch (\Exception $e) {
                    Log::warning('ProcessFinalOpenRouterStudy: Error extracting PDF content', [
                        'screening_id' => $this->finalScreening->id,
                        'study_id' => $study->id,
                        'pdf_path' => $study->pdf_path,
                        'error' => $e->getMessage(),
                    ]);
                    $fullStudyContent = 'Error extracting PDF content: ' . $e->getMessage();
                }
            } else {
                Log::info('ProcessFinalOpenRouterStudy: Study has no pdf_path', [
                    'screening_id' => $this->finalScreening->id,
                    'study_id' => $study->id,
                ]);
                $fullStudyContent = 'No PDF available for this study.';
            }

            if (!$hasPdf) {
                Log::info('ProcessFinalOpenRouterStudy: Study has no PDF, marking as excluded without AI processing', [
                    'screening_id' => $this->finalScreening->id,
                    'study_id' => $study->id,
                    'pdf_path' => $study->pdf_path,
                    'full_study_content_preview' => substr($fullStudyContent, 0, 100),
                ]);

                DB::table('final_screening_study')
                    ->updateOrInsert(
                        [
                            'final_screening_id' => $this->finalScreening->id,
                            'study_id' => $study->id,
                        ],
                        [
                            'status' => false,
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

            Log::info('ProcessFinalOpenRouterStudy: Prompt built', [
                'screening_id' => $this->finalScreening->id,
                'study_id' => $study->id,
                'prompt_preview' => substr($prompt, 0, 500),
                'inclusion_criteria_in_prompt' => str_contains(strtolower($prompt), strtolower($inclusionCriteriaText)),
                'exclusion_criteria_in_prompt' => str_contains(strtolower($prompt), strtolower($exclusionCriteriaText)),
            ]);

            $modelId = $this->finalScreening->aiModel->model_id;

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
                'temperature' => 0.7,
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

            Log::info('ProcessFinalOpenRouterStudy: API Response', [
                'screening_id' => $this->finalScreening->id,
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

                        Log::info('ProcessFinalOpenRouterStudy: Status extracted from JSON', [
                            'screening_id' => $this->finalScreening->id,
                            'study_id' => $study->id,
                            'status_value' => $statusValue,
                            'status_parsed' => $status,
                            'json_match' => $jsonMatch,
                        ]);
                    } else {
                        Log::warning('ProcessFinalOpenRouterStudy: JSON found but no status field', [
                            'screening_id' => $this->finalScreening->id,
                            'study_id' => $study->id,
                            'json_match' => $jsonMatch,
                            'json_error' => json_last_error_msg(),
                        ]);
                    }
                } else {
                    Log::warning('ProcessFinalOpenRouterStudy: No JSON found in response content', [
                        'screening_id' => $this->finalScreening->id,
                        'study_id' => $study->id,
                        'content_preview' => substr($content, 0, 200),
                    ]);
                }
            } else {
                Log::warning('ProcessFinalOpenRouterStudy: API Response not successful', [
                    'screening_id' => $this->finalScreening->id,
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

            $saved = DB::table('final_screening_study')
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

            Log::info('ProcessFinalOpenRouterStudy: Study processed and saved', [
                'screening_id' => $this->finalScreening->id,
                'study_id' => $study->id,
                'status' => $status,
                'saved' => $saved,
                'input_ai_length' => strlen($inputAi ?? ''),
                'output_ai_length' => strlen($outputAi ?? ''),
                'total_duration' => $studyDuration,
                'input_tokens' => $studyInputTokens,
                'output_tokens' => $studyOutputTokens,
                'total_tokens' => $studyTotalTokens,
                'cost' => $studyCost,
            ]);

        } catch (\Exception $e) {
            $errorPrompt = $prompt ?? 'No AI input available';

            Log::error('ProcessFinalOpenRouterStudy: Error processing study', [
                'screening_id' => $this->finalScreening->id,
                'study_id' => $study->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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
                        'output_ai' => json_encode(['error' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 1000)]),
                        'updated_at' => now(),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    ]
                );
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Extraction;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        $jsonTemplate = [];
        $jsonInstructions = [];
        foreach ($extractionData as $data) {
            $jsonTemplate[$data->name] = null;
            $jsonInstructions[] = "In the json, in the '{$data->name}' key, include the answer: {$data->value}" . ($data->note ? " + {$data->note}" : "");
        }
        $jsonTemplateString = json_encode($jsonTemplate, JSON_PRETTY_PRINT);
        $jsonInstructionsString = implode("\n", $jsonInstructions);

        $modelId = $this->extraction->aiModel->model_id;
        $study = $this->study;
        $prompt = null;

        try {
            $hasPdf = false;
            $fullStudyContent = '';

            if ($study->pdf_path) {
                try {
                    if (Storage::disk()->exists($study->pdf_path)) {
                        $pdfContent = Storage::disk()->get($study->pdf_path);
                        $tempPath = sys_get_temp_dir() . '/' . uniqid('pdf_') . '.pdf';
                        file_put_contents($tempPath, $pdfContent);

                        if (file_exists($tempPath) && filesize($tempPath) > 0) {
                            $output = [];
                            $returnVar = 0;
                            exec("pdftotext -layout -nopgbrk '" . escapeshellarg($tempPath) . "' - 2>/dev/null", $output, $returnVar);
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
                                $pdfContent = Storage::disk()->get($study->pdf_path);
                                $tempPath2 = sys_get_temp_dir() . '/' . uniqid('pdf_') . '.pdf';
                                file_put_contents($tempPath2, $pdfContent);

                                if (file_exists($tempPath2) && filesize($tempPath2) > 0) {
                                    $output2 = [];
                                    $returnVar2 = 0;
                                    exec("pdftotext '" . escapeshellarg($tempPath2) . "' - 2>/dev/null", $output2, $returnVar2);
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
                                        Log::warning('ProcessExtractionOllamaStudy: PDF text extraction returned empty or failed', [
                                            'extraction_id' => $this->extraction->id,
                                            'study_id' => $study->id,
                                            'pdf_path' => $study->pdf_path,
                                            'temp_file_size' => filesize($tempPath2) ?? 'unknown',
                                            'return_code' => $returnVar2,
                                            'pdftotext_output_preview' => substr($fullStudyContent, 0, 200),
                                        ]);
                                    }
                                } else {
                                    @unlink($tempPath2);
                                }
                            }
                        } else {
                            @unlink($tempPath);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('ProcessExtractionOllamaStudy: Error extracting PDF content', [
                        'extraction_id' => $this->extraction->id,
                        'study_id' => $study->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $fullStudyContent = 'No PDF available for this study.';
            }

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

            $jsondataReplacement = $jsonTemplateString . "\n\n" . $jsonInstructionsString;
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
                $content = $responseData['message']['content'] ?? null;

                if ($content) {
                    $jsonMatch = null;

                    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $content, $codeBlockMatches)) {
                        $jsonMatch = $codeBlockMatches[1];
                    } elseif (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
                        $jsonMatch = $matches[0];
                    }

                    if ($jsonMatch) {
                        $cleanedJson = preg_replace('/\/\/.*$/m', '', $jsonMatch);
                        $cleanedJson = preg_replace('/\/\*[\s\S]*?\*\//', '', $cleanedJson);
                        $cleanedJson = trim($cleanedJson);

                        $outputJson = json_decode($cleanedJson, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $outputJson = null;
                        }
                    }
                }
            }

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
                    $value = $outputJson[$data->name] ?? null;
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

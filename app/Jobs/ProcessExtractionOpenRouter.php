<?php

namespace App\Jobs;

use App\Models\Extraction;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessExtractionOpenRouter implements ShouldQueue
{
    use Queueable;

    protected $extraction;

    /** When true, only studies without an AI output yet are processed. */
    protected $pendingOnly;

    /**
     * Create a new job instance.
     */
    public function __construct(Extraction $extraction, bool $pendingOnly = false)
    {
        $this->extraction = $extraction;
        $this->pendingOnly = $pendingOnly;
    }

    /**
     * Execute the job: validate extraction and dispatch one ProcessExtractionOpenRouterStudy per study.
     */
    public function handle(): void
    {
        Log::info('ProcessExtractionOpenRouter job started', [
            'extraction_id' => $this->extraction->id,
        ]);

        $this->extraction->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->extraction->tipo !== 'AI OpenRouter') {
            Log::warning('ProcessExtractionOpenRouter called for non-AI OpenRouter extraction', [
                'extraction_id' => $this->extraction->id,
                'tipo' => $this->extraction->tipo
            ]);
            return;
        }

        if (!$this->extraction->template) {
            Log::error('ProcessExtractionOpenRouter: Template not found', [
                'extraction_id' => $this->extraction->id
            ]);
            return;
        }

        if (!$this->extraction->aiModel) {
            Log::error('ProcessExtractionOpenRouter: AI Model not found', [
                'extraction_id' => $this->extraction->id
            ]);
            return;
        }

        $user = $this->extraction->responsavel;
        if (!$user || !$user->openrouter_api_key) {
            Log::error('ProcessExtractionOpenRouter: OpenRouter API key not found', [
                'extraction_id' => $this->extraction->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $studies = ($this->pendingOnly ? $this->extraction->pendingStudies() : $this->extraction->eligibleStudies())
            ->orderBy('custom_id')
            ->get();

        Log::info('ProcessExtractionOpenRouter - Studies to process', [
            'extraction_id' => $this->extraction->id,
            'studies_count' => $studies->count(),
            'pending_only' => $this->pendingOnly,
        ]);

        foreach ($studies as $study) {
            ProcessExtractionOpenRouterStudy::dispatch($this->extraction, $study);
        }

        Log::info('ProcessExtractionOpenRouter: Dispatched jobs per study', [
            'extraction_id' => $this->extraction->id,
            'studies_count' => $studies->count(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\Extraction;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessExtractionOllama implements ShouldQueue
{
    use Queueable;

    protected $extraction;

    /**
     * Create a new job instance.
     */
    public function __construct(Extraction $extraction)
    {
        $this->extraction = $extraction;
    }

    /**
     * Execute the job: validate extraction and dispatch one ProcessExtractionOllamaStudy per study.
     */
    public function handle(): void
    {
        Log::info('ProcessExtractionOllama job started', [
            'extraction_id' => $this->extraction->id,
        ]);

        $this->extraction->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->extraction->tipo !== 'AI Local') {
            Log::warning('ProcessExtractionOllama called for non-AI Local extraction', [
                'extraction_id' => $this->extraction->id,
                'tipo' => $this->extraction->tipo
            ]);
            return;
        }

        if (!$this->extraction->template) {
            Log::error('ProcessExtractionOllama: Template not found', [
                'extraction_id' => $this->extraction->id
            ]);
            return;
        }

        if (!$this->extraction->aiModel) {
            Log::error('ProcessExtractionOllama: AI Model not found', [
                'extraction_id' => $this->extraction->id
            ]);
            return;
        }

        $user = $this->extraction->responsavel;
        if (!$user || !$user->ollama_url) {
            Log::error('ProcessExtractionOllama: Ollama URL not found', [
                'extraction_id' => $this->extraction->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $studies = Study::where('project_id', $this->extraction->project_id)
            ->where('status_selecao_final', 1)
            ->orderBy('custom_id')
            ->get();

        Log::info('ProcessExtractionOllama - Studies to process', [
            'extraction_id' => $this->extraction->id,
            'studies_count' => $studies->count(),
        ]);

        foreach ($studies as $study) {
            ProcessExtractionOllamaStudy::dispatch($this->extraction, $study);
        }

        Log::info('ProcessExtractionOllama: Dispatched jobs per study', [
            'extraction_id' => $this->extraction->id,
            'studies_count' => $studies->count(),
        ]);
    }
}

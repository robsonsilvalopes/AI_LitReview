<?php

namespace App\Jobs;

use App\Models\FinalScreening;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessFinalOllama implements ShouldQueue
{
    use Queueable;

    protected $finalScreening;

    /**
     * Create a new job instance.
     */
    public function __construct(FinalScreening $finalScreening)
    {
        $this->finalScreening = $finalScreening;
    }

    /**
     * Execute the job: validate screening and dispatch one ProcessFinalOllamaStudy per study.
     */
    public function handle(): void
    {
        Log::info('ProcessFinalOllama job started', [
            'screening_id' => $this->finalScreening->id,
        ]);

        $this->finalScreening->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->finalScreening->tipo !== 'AI Local') {
            Log::warning('ProcessFinalOllama called for non-AI Local screening', [
                'screening_id' => $this->finalScreening->id,
                'tipo' => $this->finalScreening->tipo
            ]);
            return;
        }

        if (!$this->finalScreening->template) {
            Log::error('ProcessFinalOllama: Template not found', [
                'screening_id' => $this->finalScreening->id
            ]);
            return;
        }

        if (!$this->finalScreening->aiModel) {
            Log::error('ProcessFinalOllama: AI Model not found', [
                'screening_id' => $this->finalScreening->id
            ]);
            return;
        }

        $user = $this->finalScreening->responsavel;
        if (!$user || !$user->ollama_url) {
            Log::error('ProcessFinalOllama: Ollama URL not found', [
                'screening_id' => $this->finalScreening->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $studies = Study::where('project_id', $this->finalScreening->project_id)
            ->where('status_selecao_inicial', 1)
            ->orderBy('custom_id')
            ->get();

        Log::info('ProcessFinalOllama - Studies to process', [
            'screening_id' => $this->finalScreening->id,
            'studies_count' => $studies->count(),
        ]);

        foreach ($studies as $study) {
            ProcessFinalOllamaStudy::dispatch($this->finalScreening, $study);
        }

        Log::info('ProcessFinalOllama: Dispatched jobs per study', [
            'screening_id' => $this->finalScreening->id,
            'studies_count' => $studies->count(),
        ]);
    }
}

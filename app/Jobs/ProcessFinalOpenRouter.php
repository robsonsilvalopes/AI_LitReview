<?php

namespace App\Jobs;

use App\Models\FinalScreening;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessFinalOpenRouter implements ShouldQueue
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
     * Execute the job: validate screening and dispatch one ProcessFinalOpenRouterStudy per study.
     */
    public function handle(): void
    {
        Log::info('ProcessFinalOpenRouter job started', [
            'screening_id' => $this->finalScreening->id,
        ]);

        $this->finalScreening->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->finalScreening->tipo !== 'AI OpenRouter') {
            Log::warning('ProcessFinalOpenRouter called for non-AI OpenRouter screening', [
                'screening_id' => $this->finalScreening->id,
                'tipo' => $this->finalScreening->tipo
            ]);
            return;
        }

        if (!$this->finalScreening->template) {
            Log::error('ProcessFinalOpenRouter: Template not found', [
                'screening_id' => $this->finalScreening->id
            ]);
            return;
        }

        if (!$this->finalScreening->aiModel) {
            Log::error('ProcessFinalOpenRouter: AI Model not found', [
                'screening_id' => $this->finalScreening->id
            ]);
            return;
        }

        $user = $this->finalScreening->responsavel;
        if (!$user || !$user->openrouter_api_key) {
            Log::error('ProcessFinalOpenRouter: OpenRouter API key not found', [
                'screening_id' => $this->finalScreening->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $studies = $this->finalScreening->pendingStudies()
            ->orderBy('custom_id')
            ->get();

        Log::info('ProcessFinalOpenRouter - Studies to process', [
            'screening_id' => $this->finalScreening->id,
            'studies_count' => $studies->count(),
        ]);

        foreach ($studies as $study) {
            ProcessFinalOpenRouterStudy::dispatch($this->finalScreening, $study);
        }

        Log::info('ProcessFinalOpenRouter: Dispatched jobs per study', [
            'screening_id' => $this->finalScreening->id,
            'studies_count' => $studies->count(),
        ]);
    }
}

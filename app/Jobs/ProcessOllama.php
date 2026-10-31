<?php

namespace App\Jobs;

use App\Models\InitialScreening;
use App\Models\Study;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOllama implements ShouldQueue
{
    use Queueable;

    protected $initialScreening;

    /** When true, only studies without a result (status NULL) are (re)processed. */
    protected bool $onlyPending;

    /**
     * Create a new job instance.
     */
    public function __construct(InitialScreening $initialScreening, bool $onlyPending = false)
    {
        $this->initialScreening = $initialScreening;
        $this->onlyPending = $onlyPending;
    }

    /**
     * Execute the job: validate screening and dispatch one ProcessOllamaStudy per study.
     */
    public function handle(): void
    {
        $this->initialScreening->load(['project', 'template', 'aiModel', 'responsavel']);

        if ($this->initialScreening->tipo !== 'AI Local') {
            Log::warning('ProcessOllama called for non-AI Local screening', [
                'screening_id' => $this->initialScreening->id,
                'tipo' => $this->initialScreening->tipo
            ]);
            return;
        }

        if (!$this->initialScreening->template) {
            Log::error('ProcessOllama: Template not found', [
                'screening_id' => $this->initialScreening->id
            ]);
            return;
        }

        if (!$this->initialScreening->aiModel) {
            Log::error('ProcessOllama: AI Model not found', [
                'screening_id' => $this->initialScreening->id
            ]);
            return;
        }

        $user = $this->initialScreening->responsavel;
        if (!$user || !$user->ollama_url) {
            Log::error('ProcessOllama: Ollama URL not found', [
                'screening_id' => $this->initialScreening->id,
                'user_id' => $user?->id
            ]);
            return;
        }

        $studies = Study::where('project_id', $this->initialScreening->project_id)
            ->where('status_duplicados_exclusao', 1)
            ->when($this->onlyPending, function ($query) {
                // Skip studies that already have an include/exclude decision in this screening
                $query->whereNotIn('id', DB::table('initial_screening_study')
                    ->where('initial_screening_id', $this->initialScreening->id)
                    ->whereNotNull('status')
                    ->select('study_id'));
            })
            ->orderBy('custom_id')
            ->get();

        foreach ($studies as $study) {
            ProcessOllamaStudy::dispatch($this->initialScreening, $study);
        }

        Log::info('ProcessOllama: Dispatched jobs per study', [
            'screening_id' => $this->initialScreening->id,
            'studies_count' => $studies->count(),
            'only_pending' => $this->onlyPending,
        ]);
    }
}

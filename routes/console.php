<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('screening:sync-ai-criteria {screening?* : Initial screening IDs (default: all AI screenings)}', function () {
    /** @var ClosureCommand $this */
    $ids = $this->argument('screening');
    $screenings = \App\Models\InitialScreening::whereIn('tipo', ['AI OpenRouter', 'AI Local'])
        ->when(!empty($ids), fn ($q) => $q->whereIn('id', $ids))
        ->orderBy('id')
        ->get();

    $service = app(\App\Services\AiScreeningCriteria::class);
    foreach ($screenings as $screening) {
        $studies = 0;
        $criteria = 0;
        \Illuminate\Support\Facades\DB::table('initial_screening_study')
            ->where('initial_screening_id', $screening->id)
            ->orderBy('id')
            ->select(['id', 'study_id', 'status', 'output_ai'])
            ->chunkById(200, function ($rows) use ($service, $screening, &$studies, &$criteria) {
                foreach ($rows as $row) {
                    $criteria += $service->syncStudy($screening, $row->study_id, $row->status, $row->output_ai);
                    $studies++;
                }
            });
        $this->info("#{$screening->id} {$screening->nome}: {$studies} studies, {$criteria} criteria recorded");
    }
})->purpose('Record AI-scored criteria (score >= 6) for AI initial screenings');

Artisan::command('screening:sync-ai-final-criteria {screening?* : Final screening IDs (default: all AI screenings)}', function () {
    /** @var ClosureCommand $this */
    $ids = $this->argument('screening');
    $screenings = \App\Models\FinalScreening::whereIn('tipo', ['AI OpenRouter', 'AI Local'])
        ->when(!empty($ids), fn ($q) => $q->whereIn('id', $ids))
        ->orderBy('id')
        ->get();

    $service = app(\App\Services\AiScreeningCriteria::class);
    foreach ($screenings as $screening) {
        $studies = 0;
        $criteria = 0;
        \Illuminate\Support\Facades\DB::table('final_screening_study')
            ->where('final_screening_id', $screening->id)
            ->orderBy('id')
            ->select(['id', 'study_id', 'status', 'output_ai'])
            ->chunkById(200, function ($rows) use ($service, $screening, &$studies, &$criteria) {
                foreach ($rows as $row) {
                    $criteria += $service->syncStudy($screening, $row->study_id, $row->status, $row->output_ai);
                    $studies++;
                }
            });
        $this->info("#{$screening->id} {$screening->nome}: {$studies} studies, {$criteria} criteria recorded");
    }
})->purpose('Record AI-scored criteria (score >= 6) for AI final screenings');

Artisan::command('studies:generate-markdown {--project= : Only this project ID} {--force : Also regenerate studies that already have Markdown}', function () {
    /** @var ClosureCommand $this */
    $query = \App\Models\Study::whereNotNull('pdf_path')->where('pdf_path', '!=', '')
        ->when($this->option('project'), fn ($q, $projectId) => $q->where('project_id', $projectId))
        ->when(!$this->option('force'), fn ($q) => $q->whereNull('md_path'));

    $count = 0;
    $query->orderBy('id')->each(function (\App\Models\Study $study) use (&$count) {
        \App\Jobs\GenerateStudyMarkdown::dispatch($study, $study->pdf_path);
        $count++;
    });

    $this->info("Queued Markdown generation for {$count} studies. The queue worker converts them in the background.");
})->purpose('Queue the PDF to Markdown conversion (pymupdf4llm) for studies whose PDF has no Markdown yet');

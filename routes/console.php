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

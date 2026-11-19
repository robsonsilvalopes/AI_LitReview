<?php

namespace App\Jobs;

use App\Models\Study;
use App\Services\StudyMarkdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Converts an uploaded study PDF into Markdown in the background (dispatched by the Study model
 * whenever its pdf_path changes).
 */
class GenerateStudyMarkdown implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 600;

    /** A study deleted before the job runs is simply skipped. */
    public $deleteWhenMissingModels = true;

    /**
     * @param string $pdfPath the PDF this job converts; if the study has another PDF by the time
     *                        the job runs, a newer job takes care of it and this one does nothing
     */
    public function __construct(public Study $study, public string $pdfPath)
    {
    }

    public function handle(StudyMarkdown $markdown): void
    {
        $study = $this->study->fresh();
        if (!$study || $study->pdf_path !== $this->pdfPath) {
            return;
        }

        try {
            $markdown->generate($study);
            Log::info('GenerateStudyMarkdown: Markdown saved', [
                'study_id' => $study->id,
                'md_path' => $study->md_path,
            ]);
        } catch (\RuntimeException $e) {
            // Unreadable PDFs (scanned, encrypted, corrupted) will not get better on retry
            Log::warning('GenerateStudyMarkdown: conversion failed', [
                'study_id' => $study->id,
                'pdf_path' => $this->pdfPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

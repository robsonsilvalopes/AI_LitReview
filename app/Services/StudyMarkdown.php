<?php

namespace App\Services;

use App\Models\Study;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The Markdown version of a study's PDF, made with pymupdf4llm (scripts/pdf_to_markdown.py)
 * and stored next to the PDF. It is the full text sent to the AI in final screening and extraction.
 */
class StudyMarkdown
{
    /**
     * Where the Markdown of a PDF is stored: same folder and name, .md extension.
     */
    public static function pathFor(string $pdfPath): string
    {
        return preg_replace('/\.pdf$/i', '', $pdfPath) . '.md';
    }

    /**
     * Convert the study's current PDF and store the Markdown, saving md_path on the study.
     *
     * @throws RuntimeException when the PDF is missing or cannot be converted
     */
    public function generate(Study $study): string
    {
        $pdfPath = $study->pdf_path;
        if (!$pdfPath) {
            throw new RuntimeException('The study has no PDF.');
        }

        $disk = Storage::disk();
        if (!$disk->exists($pdfPath)) {
            throw new RuntimeException("PDF not found in storage: {$pdfPath}");
        }

        $tempPdf = tempnam(sys_get_temp_dir(), 'study_pdf_');
        $tempMd = tempnam(sys_get_temp_dir(), 'study_md_');

        try {
            file_put_contents($tempPdf, $disk->get($pdfPath));

            $result = Process::timeout((int) config('services.pdf_markdown.timeout', 300))->run([
                config('services.pdf_markdown.python', '/opt/pdf-tools/bin/python'),
                base_path('scripts/pdf_to_markdown.py'),
                $tempPdf,
                $tempMd,
            ]);

            if (!$result->successful()) {
                $reason = match ($result->exitCode()) {
                    3 => 'no text found (the PDF may be image-based)',
                    default => trim($result->errorOutput()) ?: 'exit code ' . $result->exitCode(),
                };
                throw new RuntimeException("PDF to Markdown conversion failed: {$reason}");
            }

            $markdown = (string) file_get_contents($tempMd);
        } finally {
            @unlink($tempPdf);
            @unlink($tempMd);
        }

        $mdPath = self::pathFor($pdfPath);
        $disk->put($mdPath, $markdown);

        // Save without touching updated_at, and only if the PDF was not replaced meanwhile
        $saved = Study::whereKey($study->id)->where('pdf_path', $pdfPath)->update(['md_path' => $mdPath]);
        if ($saved) {
            $study->md_path = $mdPath;
            $study->syncOriginalAttribute('md_path');
        } else {
            $disk->delete($mdPath);
            throw new RuntimeException('The study PDF changed during the conversion.');
        }

        return $markdown;
    }

    /**
     * The study's full text for an AI prompt. Uses the stored Markdown, and converts the PDF now
     * (saving the result) for studies uploaded before Markdown existed or whose job has not run yet.
     *
     * @return array{has_text: bool, content: string} content is the text, or the reason it is missing
     */
    public function fullTextFor(Study $study): array
    {
        if (!$study->pdf_path) {
            return ['has_text' => false, 'content' => 'No PDF available for this study.'];
        }

        $disk = Storage::disk();

        try {
            if ($study->md_path && $disk->exists($study->md_path)) {
                $markdown = (string) $disk->get($study->md_path);
            } else {
                $markdown = $this->generate($study);
            }
        } catch (\Throwable $e) {
            Log::warning('StudyMarkdown: full text unavailable', [
                'study_id' => $study->id,
                'pdf_path' => $study->pdf_path,
                'error' => $e->getMessage(),
            ]);

            return [
                'has_text' => false,
                'content' => 'PDF file available but text extraction failed. ' . $e->getMessage(),
            ];
        }

        if (trim($markdown) === '') {
            return ['has_text' => false, 'content' => 'PDF file available but no text was extracted.'];
        }

        return ['has_text' => true, 'content' => $markdown];
    }
}

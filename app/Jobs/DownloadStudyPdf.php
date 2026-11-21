<?php

namespace App\Jobs;

use App\Models\Study;
use App\Services\OpenAccessPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Looks for an open-access PDF of a study by its DOI and, when found, sets it as the study's PDF
 * (which in turn queues its Markdown conversion). Queued from the Final Selection page, which
 * shows the per-study progress recorded here.
 */
class DownloadStudyPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One attempt only: a retry would count the study twice in the page's progress. */
    public $tries = 1;

    public $timeout = 300;

    public $deleteWhenMissingModels = true;

    /**
     * The contact e-mail and CORE key are read from the project owner's account when the job
     * runs, so the key is never stored in the queue payload.
     */
    public function __construct(public Study $study)
    {
    }

    /** Cache key of the progress of a project's PDF search. */
    public static function progressKey(int $projectId): string
    {
        return 'pdf_fetch_progress_' . $projectId;
    }

    /**
     * Start tracking a PDF search over the given studies.
     *
     * @param iterable<Study> $studies
     */
    public static function startProgress(int $projectId, iterable $studies, array $sources = []): array
    {
        $items = [];
        foreach ($studies as $study) {
            $items[$study->id] = [
                'id' => $study->id,
                'custom_id' => $study->custom_id,
                'name' => $study->name,
                'doi' => $study->doi,
                'status' => 'queued',
                'source' => null,
                'via' => null,
                'size' => null,
                'links' => [],
                'reason' => null,
            ];
        }

        $progress = [
            'total' => count($items),
            'done' => 0,
            'found' => 0,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'sources' => $sources,
            'items' => $items,
        ];
        Cache::put(self::progressKey($projectId), $progress, now()->addDay());

        return $progress;
    }

    public function handle(OpenAccessPdf $openAccess): void
    {
        $this->updateItem(['status' => 'searching']);
        $item = ['status' => 'not_found'];

        try {
            $study = $this->study->fresh();
            if (!$study) {
                $item = ['status' => 'skipped', 'reason' => 'Study deleted'];
            } elseif ($study->pdf_path) {
                $item = ['status' => 'skipped', 'reason' => 'Already has a PDF'];
            } else {
                $owner = $study->project?->user;
                $result = $openAccess->fetch($study, $owner?->unpaywall_email, $owner?->core_api_key);
                $item['reason'] = $result['reason'];
                $item['links'] = $result['links'];
                if ($result['path']) {
                    // The PDF may have been uploaded by hand while this job was searching
                    $study->refresh();
                    if ($study->pdf_path) {
                        Storage::disk()->delete($result['path']);
                        $item = ['status' => 'skipped', 'reason' => 'A PDF was uploaded meanwhile'];
                    } else {
                        $study->pdf_path = $result['path'];
                        $study->save();
                        $item = [
                            'status' => 'found',
                            'source' => parse_url($result['url'], PHP_URL_HOST),
                            'via' => $result['via'],
                            'size' => $result['size'],
                            'reason' => null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('DownloadStudyPdf: failed', ['study_id' => $this->study->id, 'error' => $e->getMessage()]);
            $item = ['status' => 'error', 'reason' => 'Error: ' . $e->getMessage()];
        }

        $this->updateItem($item, true);
    }

    /** Merge the fields into this study's progress entry, counting it as done when finished. */
    private function updateItem(array $fields, bool $finished = false): void
    {
        $key = self::progressKey($this->study->project_id);

        Cache::lock($key . '_lock', 10)->block(10, function () use ($key, $fields, $finished) {
            $progress = Cache::get($key);
            if (!$progress || !isset($progress['items'][$this->study->id])) {
                return;
            }

            $progress['items'][$this->study->id] = array_merge($progress['items'][$this->study->id], $fields);
            if ($finished) {
                $progress['done']++;
                if ($fields['status'] === 'found') {
                    $progress['found']++;
                }
                if ($progress['done'] >= $progress['total']) {
                    $progress['finished_at'] = now()->toIso8601String();
                }
            }
            Cache::put($key, $progress, now()->addDay());
        });
    }
}

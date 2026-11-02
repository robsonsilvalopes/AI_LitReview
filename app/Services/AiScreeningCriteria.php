<?php

namespace App\Services;

use App\Models\InitialScreening;
use Illuminate\Support\Facades\DB;

/**
 * Reads the criteria scores from an AI screening response and records them in the
 * same criteria tables used by human screening, so AI and human screenings are
 * displayed the same way.
 *
 * Expected model output (Likert templates):
 *   {"criteria": [{"id": "IC1", "score": 6, ...}, {"id": "EC2", "score": 1, ...}], "overall": "include"}
 *
 * A criterion counts as applying when its score is >= MIN_SCORE ("clearly applies").
 * Like human screening, an included study records its inclusion criteria and an
 * excluded study records its exclusion criteria.
 */
class AiScreeningCriteria
{
    public const MIN_SCORE = 6;

    /** @var array<int, array{inclusion: array<string,int>, exclusion: array<string,int>}> */
    private array $criteriaMapCache = [];

    /**
     * Extract the model's message content from a raw response body (OpenRouter or Ollama).
     */
    public static function extractContent(?string $outputAi): ?string
    {
        if ($outputAi === null || trim($outputAi) === '') {
            return null;
        }

        $data = json_decode(trim($outputAi), true);
        if (!is_array($data)) {
            return null;
        }

        return $data['choices'][0]['message']['content'] // OpenRouter
            ?? $data['message']['content']               // Ollama
            ?? null;
    }

    /**
     * Return the criteria entries ([id, score]) from a raw response body.
     *
     * @return array<int, array{id: string, score: int}>
     */
    public static function parseCriteria(?string $outputAi): array
    {
        $content = self::extractContent($outputAi);
        if ($content === null) {
            return [];
        }

        // Strip ```json fences and any text around the outermost JSON object
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $json = json_decode(substr($content, $start, $end - $start + 1), true);
        if (!is_array($json) || !isset($json['criteria']) || !is_array($json['criteria'])) {
            return [];
        }

        $entries = [];
        foreach ($json['criteria'] as $item) {
            if (!is_array($item) || !isset($item['id'], $item['score']) || !is_numeric($item['score'])) {
                continue;
            }
            $entries[] = [
                'id' => strtoupper(trim((string) $item['id'])),
                'score' => (int) $item['score'],
            ];
        }

        return $entries;
    }

    /**
     * Replace the recorded criteria for one study in one AI screening, based on its output.
     *
     * @return int number of criteria recorded
     */
    public function syncStudy(InitialScreening $screening, int $studyId, $status, ?string $outputAi): int
    {
        $status = $status === null ? null : (bool) $status;
        $map = $this->criteriaMap($screening->project_id);

        $type = $status === true ? 'inclusion' : ($status === false ? 'exclusion' : null);
        $criteriaIds = [];
        if ($type !== null) {
            foreach (self::parseCriteria($outputAi) as $entry) {
                if ($entry['score'] >= self::MIN_SCORE && isset($map[$type][$entry['id']])) {
                    $criteriaIds[] = $map[$type][$entry['id']];
                }
            }
            $criteriaIds = array_values(array_unique($criteriaIds));
        }

        DB::transaction(function () use ($screening, $studyId, $type, $criteriaIds) {
            DB::table('study_inclusion_criteria_screening')
                ->where('study_id', $studyId)
                ->where('initial_screening_id', $screening->id)
                ->delete();
            DB::table('study_exclusion_criteria_screening')
                ->where('study_id', $studyId)
                ->where('initial_screening_id', $screening->id)
                ->delete();

            if ($type === null || empty($criteriaIds)) {
                return;
            }

            $now = now();
            DB::table("study_{$type}_criteria_screening")->insert(array_map(fn ($criteriaId) => [
                'study_id' => $studyId,
                "{$type}_criteria_id" => $criteriaId,
                'initial_screening_id' => $screening->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $criteriaIds));
        });

        return count($criteriaIds);
    }

    /**
     * Map prompt IDs (custom_id, or the IC{id}/EC{id} fallback used when building the prompt)
     * to criteria primary keys.
     *
     * @return array{inclusion: array<string,int>, exclusion: array<string,int>}
     */
    private function criteriaMap(int $projectId): array
    {
        if (isset($this->criteriaMapCache[$projectId])) {
            return $this->criteriaMapCache[$projectId];
        }

        $map = ['inclusion' => [], 'exclusion' => []];
        foreach (['inclusion' => 'IC', 'exclusion' => 'EC'] as $type => $prefix) {
            $rows = DB::table("{$type}_criterias")->where('project_id', $projectId)->get(['id', 'custom_id']);
            foreach ($rows as $row) {
                $key = $row->custom_id ? strtoupper(trim($row->custom_id)) : $prefix . $row->id;
                $map[$type][$key] = (int) $row->id;
            }
        }

        return $this->criteriaMapCache[$projectId] = $map;
    }
}

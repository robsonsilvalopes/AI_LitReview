<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Asks the AI, in data extraction, to quote the passages of the study's Markdown each answer
 * came from (an "_evidence" key next to the answers), and reads those quotes back.
 */
class ExtractionEvidence
{
    public const KEY = '_evidence';

    /** At most this many quotes per field, each cut to this many characters. */
    private const MAX_QUOTES = 5;
    private const MAX_QUOTE_LENGTH = 600;

    /**
     * The [[jsondata]] replacement: the JSON skeleton (answers plus "_evidence") and the instructions.
     * Keys are the field IDs ("ED1"): stable when a field is renamed and hard to mistype; the field
     * name goes in the instruction, to tell the model what the key holds.
     *
     * @param Collection $extractionData the project's extraction fields (custom_id, name, value, note)
     */
    public function jsonData(Collection $extractionData): string
    {
        $fields = $extractionData->sortBy(fn ($data) => $this->keyFor($data), SORT_NATURAL)->values();

        $template = [];
        $instructions = [];
        foreach ($fields as $data) {
            $key = $this->keyFor($data);
            $template[$key] = null;
            $instructions[] = "In the json, in the '{$key}' key ({$data->name}), include the answer: {$data->value}" . ($data->note ? " + {$data->note}" : '');
        }

        $template[self::KEY] = (object) $fields->mapWithKeys(fn ($data) => [$this->keyFor($data) => []])->all();

        $instructions[] = '';
        $instructions[] = "In the json, in the '" . self::KEY . "' key, for each key above, list the passages of the study that support the answer, "
            . 'copied word for word from the study text (exact sentences, no paraphrasing, no ellipsis inside a passage). '
            . 'Use one or more short passages of at most 2 sentences each, at most ' . self::MAX_QUOTES . ' per key. '
            . 'Use an empty list when the answer is not stated in the study. '
            . 'A passage is always text copied from the study: never put the answer itself, a summary, a list of values or "not reported" in it. '
            . 'For values taken from a table, copy the table caption or the sentence of the text that reports them. '
            . "Always include the '" . self::KEY . "' key, even if an example elsewhere in these instructions shows the JSON without it. "
            . "Use the field IDs above ('" . $this->keyFor($fields->first()) . "', ...) as keys, both for the answers and inside '" . self::KEY . "'.";

        return json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\n" . implode("\n", $instructions);
    }

    /**
     * Read the JSON object out of the model's reply: a ```json block, or the outermost {...}.
     * Comments are only stripped when the JSON does not parse as it is, since quotes may hold URLs.
     */
    public function parseJson(?string $content): ?array
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $candidates = [];
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $content, $block)) {
            $candidates[] = $block[1];
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($content, $start, $end - $start + 1);
        }

        foreach ($candidates as $json) {
            foreach ([$json, $this->withoutComments($json)] as $attempt) {
                $decoded = json_decode(trim($attempt), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * The JSON key of a field: its ID ("ED1"), or its name for a field without an ID.
     */
    public function keyFor(?object $data): string
    {
        if (!$data) {
            return 'ED1';
        }

        return trim((string) ($data->custom_id ?? '')) !== '' ? trim($data->custom_id) : $data->name;
    }

    /**
     * The answer the model gave for a field, by its ID ("ED1"). Replies made before the keys were
     * IDs, or by models that used the names anyway, are read by the field name.
     */
    public function answerFor(?array $outputJson, object $data): mixed
    {
        if (!$outputJson) {
            return null;
        }

        return $outputJson[$this->keyFor($data)] ?? $outputJson[$data->name] ?? null;
    }

    /**
     * The quotes per field, keyed by extraction_data id, for the fields that have any.
     *
     * @return array<int, string[]>
     */
    public function fromOutput(?array $outputJson, Collection $extractionData): array
    {
        $evidence = $outputJson[self::KEY] ?? null;
        if (!is_array($evidence)) {
            return [];
        }

        $byField = [];
        foreach ($extractionData as $data) {
            $value = $this->answerFor($outputJson, $data);
            $answer = is_scalar($value) ? $this->normalized((string) $value) : '';
            $quotes = $this->answerFor($evidence, $data);
            if (is_string($quotes)) {
                $quotes = [$quotes];
            }
            if (!is_array($quotes)) {
                continue;
            }

            $clean = collect($quotes)
                ->filter(fn ($quote) => is_string($quote))
                ->map(fn ($quote) => mb_substr(trim(preg_replace('/\s+/u', ' ', $quote)), 0, self::MAX_QUOTE_LENGTH))
                ->filter(fn ($quote) => mb_strlen($quote) >= 3)
                // Not passages: placeholders, or the answer repeated
                ->reject(fn ($quote) => preg_match('/^(not reported|not applicable|nr|na|n\/a|none|not stated|not available|-)\.?$/i', $quote)
                    || ($answer !== '' && $this->normalized($quote) === $answer))
                ->unique()
                ->take(self::MAX_QUOTES)
                ->values()
                ->all();

            if ($clean) {
                $byField[(int) $data->id] = $clean;
            }
        }

        return $byField;
    }

    private function normalized(string $text): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $text));
    }

    /**
     * Strip // and /* *\/ comments outside strings (some local models add them to JSON).
     */
    private function withoutComments(string $json): string
    {
        return preg_replace('~("(?:\\\\.|[^"\\\\])*")|//[^\n]*|/\*[\s\S]*?\*/~', '$1', $json);
    }
}

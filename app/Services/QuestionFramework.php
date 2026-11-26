<?php

namespace App\Services;

use App\Models\Project;

/**
 * Research question frameworks (PICO and variants, PCC, SPIDER, PEO, custom) of a project:
 * definitions, normalized elements, the [[pico]] template text, and search string drafts
 * (one OR block of keywords per element, blocks joined with AND) in each database syntax.
 */
class QuestionFramework
{
    /** key => [name, typical use, [element key => element name]] */
    public const FRAMEWORKS = [
        'pico' => ['PICO', 'Health, interventions', [
            'P' => 'Population / Problem', 'I' => 'Intervention', 'C' => 'Comparison', 'O' => 'Outcome']],
        'picos' => ['PICOS', 'Health, interventions with study design', [
            'P' => 'Population / Problem', 'I' => 'Intervention', 'C' => 'Comparison', 'O' => 'Outcome', 'S' => 'Study design']],
        'picot' => ['PICOT', 'Health, interventions over time', [
            'P' => 'Population / Problem', 'I' => 'Intervention', 'C' => 'Comparison', 'O' => 'Outcome', 'T' => 'Time']],
        'picoc' => ['PICOC', 'Software engineering (Kitchenham)', [
            'P' => 'Population', 'I' => 'Intervention', 'C' => 'Comparison', 'O' => 'Outcome', 'Co' => 'Context']],
        'pcc' => ['PCC', 'Scoping reviews (JBI)', [
            'P' => 'Population', 'C' => 'Concept', 'Co' => 'Context']],
        'spider' => ['SPIDER', 'Qualitative and mixed-methods studies', [
            'S' => 'Sample', 'PI' => 'Phenomenon of Interest', 'D' => 'Design', 'E' => 'Evaluation', 'R' => 'Research type']],
        'peo' => ['PEO', 'Observational studies', [
            'P' => 'Population', 'E' => 'Exposure', 'O' => 'Outcome']],
        'custom' => ['Custom', 'Your own elements', []],
    ];

    /**
     * Elements used in the search string by default; the others usually describe the scope
     * (comparison, outcome, context, design...) and are only searched when the user says so.
     */
    private const SEARCHED_BY_DEFAULT = [
        'pico' => ['P', 'I'], 'picos' => ['P', 'I'], 'picot' => ['P', 'I'], 'picoc' => ['P', 'I'],
        'pcc' => ['P', 'C'], 'spider' => ['S', 'PI'], 'peo' => ['P', 'E'],
    ];

    /** Search string syntaxes of the drafts. */
    public const SYNTAXES = [
        'generic' => 'Generic boolean',
        'scopus' => 'Scopus',
        'ieee' => 'IEEE Xplore',
        'pubmed' => 'PubMed',
        'openalex' => 'OpenAlex',
    ];

    /**
     * Frameworks for the page: key => [name, use, elements as [key, label]].
     */
    public static function definitions(): array
    {
        $out = [];
        foreach (self::FRAMEWORKS as $key => [$name, $use, $elements]) {
            $out[$key] = [
                'name' => $name,
                'use' => $use,
                'elements' => array_map(fn ($k, $label) => [
                    'key' => $k,
                    'label' => $label,
                    'search' => in_array($k, self::SEARCHED_BY_DEFAULT[$key] ?? [], true),
                ], array_keys($elements), $elements),
            ];
        }

        return $out;
    }

    /**
     * Validate and normalize the elements sent by the page.
     *
     * @return array<int, array{key: string, label: string, description: string, keywords: string[], not_applicable: bool, search: bool}>
     */
    public function sanitize(string $framework, array $elements): array
    {
        $allowed = self::FRAMEWORKS[$framework][2] ?? [];
        $clean = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            $key = mb_substr(trim((string) ($element['key'] ?? '')), 0, 10);
            $label = mb_substr(trim((string) ($element['label'] ?? '')), 0, 100);
            if ($framework !== 'custom') {
                if (!isset($allowed[$key])) {
                    continue;
                }
                $label = $allowed[$key];
            } elseif ($label === '') {
                continue;
            }
            if ($key === '') {
                $key = mb_substr(preg_replace('/[^A-Za-z]/', '', $label), 0, 3) ?: 'E' . (count($clean) + 1);
            }
            $clean[] = [
                'key' => $key,
                'label' => $label,
                'description' => mb_substr(trim((string) ($element['description'] ?? '')), 0, 5000),
                'keywords' => $this->keywords($element['keywords'] ?? ''),
                'not_applicable' => !empty($element['not_applicable']),
                'search' => !empty($element['search']),
            ];
            if (count($clean) >= 12) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Keywords typed one per line or separated by ";" or ",".
     *
     * @return string[]
     */
    public function keywords($input): array
    {
        $parts = is_array($input) ? $input : preg_split('/[\r\n;,]+/', (string) $input);
        $out = [];
        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/', ' ', (string) $part), " \t\"'");
            if ($part !== '' && !in_array(mb_strtolower($part), array_map('mb_strtolower', $out), true)) {
                $out[] = mb_substr($part, 0, 200);
            }
        }

        return array_slice($out, 0, 60);
    }

    /** The project's framework name, or null when none is set. */
    public function name(Project $project): ?string
    {
        return self::FRAMEWORKS[$project->question_framework][0] ?? null;
    }

    /**
     * The elements with content (description or keywords), in order.
     */
    public function filledElements(Project $project): array
    {
        if (!$this->name($project)) {
            return [];
        }

        return array_values(array_filter(
            $project->question_framework_elements ?? [],
            fn ($e) => !empty($e['not_applicable']) || trim($e['description'] ?? '') !== '' || !empty($e['keywords'])
        ));
    }

    /**
     * The [[pico]] template variable: the framework and each element's description and keywords.
     */
    public function text(Project $project): string
    {
        $elements = $this->filledElements($project);
        if (!$elements) {
            return '';
        }

        $lines = ['Research question framework (' . $this->name($project) . '):'];
        foreach ($elements as $e) {
            $line = '- ' . $e['label'] . ' (' . $e['key'] . '): ';
            if (!empty($e['not_applicable'])) {
                $line .= 'not applicable to this review.';
            } else {
                $description = trim($e['description'] ?? '');
                $line .= $description !== '' ? rtrim($description, '.') . '.' : '(no description)';
                if (!empty($e['keywords'])) {
                    $line .= ' Keywords: ' . implode('; ', $e['keywords']) . '.';
                }
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Search string drafts, one per syntax, from the elements marked for search that have keywords.
     *
     * @return array{drafts: array<string, string>, used: string[], skipped: string[]}
     */
    public function searchDrafts(Project $project): array
    {
        $blocks = [];
        $used = [];
        $skipped = [];
        foreach ($project->question_framework_elements ?? [] as $e) {
            if (!empty($e['not_applicable']) || empty($e['search'])) {
                continue;
            }
            if (empty($e['keywords'])) {
                $skipped[] = $e['label'];
                continue;
            }
            $blocks[] = $e['keywords'];
            $used[] = $e['label'];
        }

        $range = $this->dateRange($project);
        $drafts = [];
        $dateNotes = [];
        if ($blocks) {
            foreach (array_keys(self::SYNTAXES) as $syntax) {
                $drafts[$syntax] = $this->draft($blocks, $syntax);
                if ($range) {
                    [$clause, $note] = $this->dateLimit($syntax, ...$range);
                    if ($clause !== null) {
                        $drafts[$syntax] .= ' AND ' . $clause;
                    }
                    $dateNotes[$syntax] = $note;
                }
            }
        }

        return [
            'drafts' => $drafts,
            'used' => $used,
            'skipped' => $skipped,
            'date_range' => $range ? ['from' => $range[0]->format('Y-m-d'), 'to' => $range[1]->format('Y-m-d')] : null,
            'date_notes' => $dateNotes,
        ];
    }

    /**
     * The search's publication date range, when the project limits it.
     *
     * @return array{0: \Carbon\CarbonInterface, 1: \Carbon\CarbonInterface}|null
     */
    public function dateRange(Project $project): ?array
    {
        if (!$project->search_date_limit || !$project->search_date_from || !$project->search_date_to) {
            return null;
        }

        return [$project->search_date_from, $project->search_date_to];
    }

    /**
     * The date range in a database's syntax: the clause added to the draft (null when the database
     * only limits dates with a filter) and a note on how the range is applied.
     *
     * @return array{0: ?string, 1: string}
     */
    private function dateLimit(string $syntax, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $fromYear = (int) $from->format('Y');
        $toYear = (int) $to->format('Y');
        $years = $fromYear === $toYear ? (string) $fromYear : $fromYear . '–' . $toYear;
        $wholeYears = $from->format('m-d') === '01-01' && $to->format('m-d') === '12-31';

        return match ($syntax) {
            'scopus' => [
                'PUBYEAR > ' . ($fromYear - 1) . ' AND PUBYEAR < ' . ($toYear + 1),
                'Added to the string as PUBYEAR (' . $years . ').'
                    . ($wholeYears ? '' : ' Scopus limits by year only, so the days and months of the range are not applied.'),
            ],
            'pubmed' => [
                '("' . $from->format('Y/m/d') . '"[dp] : "' . $to->format('Y/m/d') . '"[dp])',
                'Added to the string as a publication date [dp] range.',
            ],
            'ieee' => [
                null,
                'Not part of the string: set Publication Year ' . $years . ' in the IEEE Xplore filters.'
                    . ($wholeYears ? '' : ' IEEE Xplore limits by year only.'),
            ],
            'openalex' => [
                null,
                'Not part of the string: add the filter from_publication_date:' . $from->format('Y-m-d')
                    . ',to_publication_date:' . $to->format('Y-m-d') . '.',
            ],
            default => [
                null,
                'Limit the publication date to ' . $from->format('Y-m-d') . ' – ' . $to->format('Y-m-d') . ' in each database.',
            ],
        };
    }

    /** A keyword written in parentheses, e.g. ((software OR app*) AND (test* OR verification)). */
    public static function isExpression(string $keyword): bool
    {
        return str_starts_with($keyword, '(') && str_ends_with($keyword, ')');
    }

    /** @param array<int, string[]> $blocks */
    private function draft(array $blocks, string $syntax): string
    {
        $quote = fn (string $term) => str_contains($term, ' ') || str_contains($term, '-') ? '"' . str_replace('"', '', $term) . '"' : $term;
        // PubMed tags every term, also inside a boolean expression: ("heart failure" OR HF) -> ("heart failure"[tiab] OR HF[tiab])
        $tagTerms = fn (string $expression) => preg_replace_callback(
            '/"[^"]*"|[^\s()"]+/',
            fn ($m) => in_array(strtoupper($m[0]), ['AND', 'OR', 'NOT'], true) ? $m[0] : $m[0] . '[tiab]',
            $expression
        );
        $term = function (string $t) use ($syntax, $quote, $tagTerms) {
            // A keyword written as "( ... )" is a boolean expression kept as it is
            if (self::isExpression($t)) {
                return $syntax === 'pubmed' ? $tagTerms($t) : $t;
            }

            return $syntax === 'pubmed' ? $quote($t) . '[tiab]' : $quote($t);
        };

        $parts = array_map(
            fn (array $keywords) => '(' . implode(' OR ', array_map($term, $keywords)) . ')',
            $blocks
        );
        $joined = implode(' AND ', $parts);

        return match ($syntax) {
            'scopus' => 'TITLE-ABS-KEY(' . implode(' AND ', $parts) . ')',
            default => $joined,
        };
    }
}

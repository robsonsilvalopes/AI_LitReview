<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * PRISMA 2020 flow diagram (new systematic reviews, databases and registers only) of a project:
 * the numbers come from the studies' phase decisions and can be adjusted by hand for work done
 * outside the tool; svg() draws the diagram in the PRISMA 2020 layout.
 */
class PrismaFlow
{
    /** Numbers the user can adjust, with their labels in the adjustment form. */
    public const ADJUSTABLE = [
        'registers' => 'Records identified from registers',
        'duplicates' => 'Duplicate records removed',
        'automation' => 'Records marked as ineligible by automation tools',
        'other_removed' => 'Records removed for other reasons',
        'screened' => 'Records screened',
        'screening_excluded' => 'Records excluded (screening)',
        'sought' => 'Reports sought for retrieval',
        'not_retrieved' => 'Reports not retrieved',
        'assessed' => 'Reports assessed for eligibility',
        'assessed_excluded' => 'Reports excluded (eligibility)',
        'included' => 'Studies included in review',
        'reports_included' => 'Reports of included studies',
    ];

    /**
     * The flow numbers computed from the project's studies.
     *
     * @return array{databases: array<int, array{name: string, count: int}>, identified: int,
     *               numbers: array<string, int>, reasons: array<int, array{label: string, count: int}>,
     *               warnings: string[]}
     */
    public function compute(Project $project): array
    {
        $studies = $project->studies()
            ->leftJoin('search_strings', 'search_strings.id', '=', 'studies.search_string_id')
            ->get([
                'studies.id', 'studies.source', 'search_strings.database as search_database',
                'studies.status_duplicados_exclusao', 'studies.status_selecao_inicial',
                'studies.status_selecao_final', 'studies.pdf_path',
            ]);

        // Records identified per database: the search string's database, else the study's source
        $databases = [];
        foreach ($studies as $study) {
            $name = trim((string) ($study->search_database ?: $study->source)) ?: 'Source not specified';
            $databases[$name] = ($databases[$name] ?? 0) + 1;
        }
        arsort($databases);

        $isFalse = fn ($v) => $v !== null && !$v;
        $isTrue = fn ($v) => $v !== null && (bool) $v;

        $remaining = $studies->reject(fn ($s) => $isFalse($s->status_duplicados_exclusao));
        $sought = $studies->filter(fn ($s) => $isTrue($s->status_selecao_inicial));
        $notRetrieved = $sought->filter(fn ($s) => $s->status_selecao_final === null && empty($s->pdf_path));
        $assessedExcluded = $sought->filter(fn ($s) => $isFalse($s->status_selecao_final));
        $included = $studies->filter(fn ($s) => $isTrue($s->status_selecao_final));

        $numbers = [
            'registers' => 0,
            'duplicates' => $studies->count() - $remaining->count(),
            'automation' => 0,
            'other_removed' => 0,
            'screened' => $remaining->count(),
            'screening_excluded' => $remaining->filter(fn ($s) => $isFalse($s->status_selecao_inicial))->count(),
            'sought' => $sought->count(),
            'not_retrieved' => $notRetrieved->count(),
            'assessed' => $sought->count() - $notRetrieved->count(),
            'assessed_excluded' => $assessedExcluded->count(),
            'included' => $included->count(),
            'reports_included' => $included->count(),
        ];

        // Reasons for excluding reports: the exclusion criteria marked in the final selection
        $reasons = [];
        $excludedIds = $assessedExcluded->pluck('id')->all();
        if ($excludedIds) {
            $rows = DB::table('study_exclusion_criteria')
                ->join('exclusion_criterias', 'exclusion_criterias.id', '=', 'study_exclusion_criteria.exclusion_criteria_id')
                ->where('study_exclusion_criteria.type', 'final')
                ->whereIn('study_exclusion_criteria.study_id', $excludedIds)
                ->groupBy('exclusion_criterias.id', 'exclusion_criterias.custom_id', 'exclusion_criterias.exclusion_criteria')
                ->orderByDesc(DB::raw('COUNT(DISTINCT study_exclusion_criteria.study_id)'))
                ->get([
                    'exclusion_criterias.custom_id',
                    'exclusion_criterias.exclusion_criteria',
                    DB::raw('COUNT(DISTINCT study_exclusion_criteria.study_id) as total'),
                ]);
            foreach ($rows as $row) {
                $label = trim(($row->custom_id ? $row->custom_id . ': ' : '') . $row->exclusion_criteria);
                $reasons[] = ['label' => $label, 'count' => (int) $row->total];
            }
            $withReason = DB::table('study_exclusion_criteria')
                ->where('type', 'final')->whereIn('study_id', $excludedIds)
                ->distinct()->count('study_id');
            if ($withReason < count($excludedIds)) {
                $reasons[] = ['label' => 'No reason recorded', 'count' => count($excludedIds) - $withReason];
            }
        }

        $warnings = [];
        $undeduplicated = $studies->filter(fn ($s) => $s->status_duplicados_exclusao === null)->count();
        $screeningPending = $remaining->filter(fn ($s) => $s->status_selecao_inicial === null)->count();
        $assessmentPending = $sought->filter(fn ($s) => $s->status_selecao_final === null && !empty($s->pdf_path))->count();
        if ($undeduplicated) {
            $warnings[] = "{$undeduplicated} records have not gone through duplicate removal yet; they are counted as screened.";
        }
        if ($screeningPending) {
            $warnings[] = "{$screeningPending} screened records have no include/exclude decision in the initial selection (pending or N/A); they are neither excluded nor sought for retrieval.";
        }
        if ($assessmentPending) {
            $warnings[] = "{$assessmentPending} reports with a PDF have no include/exclude decision in the final selection yet; they are counted as assessed but neither excluded nor included.";
        }
        if ($notRetrieved->count()) {
            $warnings[] = "{$notRetrieved->count()} studies included in the initial selection have no PDF and no final decision; they are counted as reports not retrieved.";
        }
        $reasonTotal = array_sum(array_column($reasons, 'count'));
        if ($reasonTotal > $numbers['assessed_excluded']) {
            $warnings[] = 'Some excluded reports have more than one exclusion reason, so the reasons add up to more than the reports excluded.';
        }

        return [
            'databases' => array_map(fn ($name, $count) => ['name' => $name, 'count' => $count], array_keys($databases), $databases),
            'identified' => $studies->count(),
            'numbers' => $numbers,
            'reasons' => $reasons,
            'warnings' => $warnings,
        ];
    }

    /**
     * The computed flow with the project's manual adjustments applied.
     */
    public function resolve(Project $project): array
    {
        $flow = $this->compute($project);
        $adjustments = $project->prisma_flow ?? [];

        $flow['computed'] = $flow['numbers'];
        $flow['adjusted'] = [];
        foreach (self::ADJUSTABLE as $key => $label) {
            $value = $adjustments['numbers'][$key] ?? null;
            if ($value !== null && $value !== '') {
                $flow['numbers'][$key] = (int) $value;
                $flow['adjusted'][$key] = (int) $value !== $flow['computed'][$key];
            }
        }

        // Derived totals follow the adjusted numbers unless they were adjusted themselves
        $isSet = fn ($key) => isset($adjustments['numbers'][$key]) && $adjustments['numbers'][$key] !== '';
        $n = &$flow['numbers'];
        if (!$isSet('screened')) {
            $n['screened'] = max(0, $flow['identified'] + $n['registers'] - $n['duplicates'] - $n['automation'] - $n['other_removed']);
        }
        if (!$isSet('assessed')) {
            $n['assessed'] = max(0, $n['sought'] - $n['not_retrieved']);
        }
        if (!$isSet('reports_included')) {
            $n['reports_included'] = $n['included'];
        }
        unset($n);

        $flow['reasons_text'] = $adjustments['reasons_text'] ?? '';
        $customReasons = $this->parseReasons($flow['reasons_text']);
        if ($customReasons) {
            $flow['reasons'] = $customReasons;
        }

        return $flow;
    }

    /**
     * Reasons typed one per line as "Reason: 12" (or "Reason (n = 12)").
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function parseReasons(?string $text): array
    {
        $reasons = [];
        foreach (preg_split('/\R/', (string) $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(.*?)\s*(?:\(\s*n\s*=\s*(\d+)\s*\)|[:;=-]\s*(\d+))\s*$/i', $line, $m)) {
                $reasons[] = ['label' => trim($m[1]), 'count' => (int) ($m[2] !== '' ? $m[2] : $m[3])];
            } else {
                $reasons[] = ['label' => $line, 'count' => 0];
            }
        }

        return $reasons;
    }

    // ---------------------------------------------------------------- SVG

    private const WIDTH = 1060;
    private const LEFT_X = 80;
    private const RIGHT_X = 600;
    private const BOX_W = 440;
    private const LINE_H = 18;
    private const PAD = 12;
    private const GAP = 36;
    private const FONT = 13;

    /**
     * The diagram as a standalone SVG document (white background, PRISMA 2020 layout).
     */
    public function svg(array $flow, string $title = ''): string
    {
        $n = $flow['numbers'];
        $fmt = fn (int $v) => number_format($v, 0, '.', ',');

        $identified = ['Records identified from*:', 'Databases (n = ' . $fmt($flow['identified']) . ')'];
        foreach ($flow['databases'] as $db) {
            $identified[] = '    ' . $db['name'] . ' (n = ' . $fmt($db['count']) . ')';
        }
        $identified[] = 'Registers (n = ' . $fmt($n['registers']) . ')';

        $reasons = ['Reports excluded:'];
        if (empty($flow['reasons'])) {
            $reasons[] = '    (n = ' . $fmt($n['assessed_excluded']) . ')';
        } else {
            foreach ($flow['reasons'] as $r) {
                $reasons[] = '    ' . $r['label'] . ' (n = ' . $fmt($r['count']) . ')';
            }
        }

        $rows = [
            ['phase' => 'Identification',
             'left' => $identified,
             'right' => ['Records removed before screening:',
                         '    Duplicate records removed (n = ' . $fmt($n['duplicates']) . ')',
                         '    Records marked as ineligible by automation tools (n = ' . $fmt($n['automation']) . ')',
                         '    Records removed for other reasons (n = ' . $fmt($n['other_removed']) . ')']],
            ['phase' => 'Screening',
             'left' => ['Records screened', '(n = ' . $fmt($n['screened']) . ')'],
             'right' => ['Records excluded**', '(n = ' . $fmt($n['screening_excluded']) . ')']],
            ['phase' => 'Screening',
             'left' => ['Reports sought for retrieval', '(n = ' . $fmt($n['sought']) . ')'],
             'right' => ['Reports not retrieved', '(n = ' . $fmt($n['not_retrieved']) . ')']],
            ['phase' => 'Screening',
             'left' => ['Reports assessed for eligibility', '(n = ' . $fmt($n['assessed']) . ')'],
             'right' => $reasons],
            ['phase' => 'Included',
             'left' => ['Studies included in review', '(n = ' . $fmt($n['included']) . ')',
                        'Reports of included studies', '(n = ' . $fmt($n['reports_included']) . ')'],
             'right' => null],
        ];

        $out = [];
        $y = 20;
        if ($title !== '') {
            $out[] = $this->text(self::WIDTH / 2, $y + 6, $title, 'middle', 15, true);
            $y += 24;
        }

        // Header band
        $out[] = sprintf('<rect x="%d" y="%d" width="%d" height="34" rx="6" fill="#fbe5a3" stroke="#c9a227"/>', self::LEFT_X, $y, self::RIGHT_X + self::BOX_W - self::LEFT_X);
        $out[] = $this->text((self::LEFT_X + self::RIGHT_X + self::BOX_W) / 2, $y + 22, 'Identification of studies via databases and registers', 'middle', 14, true);
        $y += 34 + 24;

        $phaseSpans = [];
        $prevLeftBottom = null;
        foreach ($rows as $row) {
            $left = $this->wrapLines($row['left']);
            $right = $row['right'] ? $this->wrapLines($row['right']) : null;
            $leftH = count($left) * self::LINE_H + 2 * self::PAD;
            $rightH = $right ? count($right) * self::LINE_H + 2 * self::PAD : 0;
            $h = max($leftH, $rightH);

            if ($prevLeftBottom !== null) {
                $out[] = $this->arrow(self::LEFT_X + self::BOX_W / 2, $prevLeftBottom, self::LEFT_X + self::BOX_W / 2, $y);
            }
            $out[] = $this->box(self::LEFT_X, $y, self::BOX_W, $h, $left);
            if ($right) {
                $out[] = $this->box(self::RIGHT_X, $y, self::BOX_W, $h, $right);
                $out[] = $this->arrow(self::LEFT_X + self::BOX_W, $y + $h / 2, self::RIGHT_X, $y + $h / 2);
            }

            $phaseSpans[$row['phase']] ??= ['top' => $y, 'bottom' => $y + $h];
            $phaseSpans[$row['phase']]['bottom'] = $y + $h;
            $prevLeftBottom = $y + $h;
            $y += $h + self::GAP;
        }

        // Phase bars on the left (rotated labels)
        foreach ($phaseSpans as $phase => $span) {
            $h = $span['bottom'] - $span['top'];
            $out[] = sprintf('<rect x="14" y="%d" width="44" height="%d" rx="6" fill="#a8c6e8" stroke="#5b87b8"/>', $span['top'], $h);
            $cx = 36;
            $cy = $span['top'] + $h / 2;
            $out[] = sprintf('<g transform="translate(%d %d) rotate(-90)"><text x="0" y="5" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="14" font-weight="bold" fill="#1f2d3d">%s</text></g>',
                $cx, $cy, $this->esc($phase));
        }

        $y += 4;
        $notes = [
            '*Number of records identified from each database or register searched.',
            '**Records excluded by a human or by automation tools in the screening of titles and abstracts.',
            'Source: Page MJ, et al. The PRISMA 2020 statement. BMJ 2021;372:n71. doi: 10.1136/bmj.n71',
        ];
        foreach ($notes as $note) {
            $out[] = $this->text(self::LEFT_X, $y, $note, 'start', 11, false, '#555');
            $y += 16;
        }
        $height = $y + 10;

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::WIDTH . '" height="' . $height . '" viewBox="0 0 ' . self::WIDTH . ' ' . $height . '" role="img" aria-label="PRISMA 2020 flow diagram">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . implode('', $out)
            . '</svg>';
    }

    /** Wrap each line to the box width, keeping the indentation of indented lines. */
    private function wrapLines(array $lines): array
    {
        $max = (int) floor((self::BOX_W - 2 * self::PAD) / (self::FONT * 0.52));
        $wrapped = [];
        foreach ($lines as $line) {
            $indent = preg_match('/^(\s+)/', $line, $m) ? $m[1] : '';
            $chunks = $this->wrap(trim($line), $max - mb_strlen($indent));
            foreach ($chunks as $i => $chunk) {
                $wrapped[] = ['text' => $chunk, 'indent' => $indent !== '' ? ($i === 0 ? 14 : 26) : ($i === 0 ? 0 : 12)];
            }
        }

        return $wrapped;
    }

    /** Word wrap by characters (not bytes), breaking words longer than the width. */
    private function wrap(string $text, int $width): array
    {
        $lines = [];
        $current = '';
        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            while (mb_strlen($word) > $width) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) > $width && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '' || !$lines) {
            $lines[] = $current;
        }

        return $lines;
    }

    private function box(int $x, int $y, int $w, int $h, array $lines): string
    {
        $svg = sprintf('<rect x="%d" y="%d" width="%d" height="%d" rx="4" fill="#ffffff" stroke="#333" stroke-width="1.2"/>', $x, $y, $w, $h);
        $textTop = $y + self::PAD + (int) round(($h - 2 * self::PAD - count($lines) * self::LINE_H) / 2) + 13;
        foreach ($lines as $i => $line) {
            $svg .= $this->text($x + self::PAD + $line['indent'], $textTop + $i * self::LINE_H, $line['text']);
        }

        return $svg;
    }

    private function arrow(float $x1, float $y1, float $x2, float $y2): string
    {
        // Arrowhead drawn as a triangle (markers are not rendered by every SVG tool)
        $angle = atan2($y2 - $y1, $x2 - $x1);
        $len = 10;
        $spread = 0.45;
        $p1 = [$x2 - $len * cos($angle - $spread), $y2 - $len * sin($angle - $spread)];
        $p2 = [$x2 - $len * cos($angle + $spread), $y2 - $len * sin($angle + $spread)];
        $shaftEnd = [$x2 - ($len - 2) * cos($angle), $y2 - ($len - 2) * sin($angle)];

        return sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#333" stroke-width="1.4"/>', $x1, $y1, $shaftEnd[0], $shaftEnd[1])
            . sprintf('<polygon points="%.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="#333"/>', $x2, $y2, $p1[0], $p1[1], $p2[0], $p2[1]);
    }

    private function text(float $x, float $y, string $text, string $anchor = 'start', int $size = self::FONT, bool $bold = false, string $color = '#111'): string
    {
        return sprintf('<text x="%.1f" y="%.1f" text-anchor="%s" font-family="Arial, Helvetica, sans-serif" font-size="%d"%s fill="%s">%s</text>',
            $x, $y, $anchor, $size, $bold ? ' font-weight="bold"' : '', $color, $this->esc($text));
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

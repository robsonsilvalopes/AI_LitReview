<?php

namespace App\Services;

use App\Models\Project;

/**
 * The steps of a systematic review in the project page's side menu, each marked done or missing.
 * Phase steps use the same rules as the progress cards of the page (ProjectController::show).
 */
class ProjectSteps
{
    /** Route names of the project's main pages => the step they show. */
    public const PAGE_STEPS = [
        'projects.studies' => 'search',
        'projects.duplicates' => 'duplicates',
        'projects.initial' => 'initial',
        'projects.final' => 'final',
        'projects.extraction' => 'extraction',
        'projects.report' => 'report',
        'projects.prisma' => 'prisma',
    ];

    /** The steps of a project, computing its phase statistics. */
    public function forProject(Project $project): array
    {
        return $this->build($project, app(ProjectStatistics::class)->compute($project));
    }

    /**
     * @param array $statistics the phase statistics (ProjectStatistics::compute)
     * @return array<int, array{title: string, steps: array<int, array{key: string, label: string, icon: string, done: bool, detail: string, href: string, anchor: bool}>}>
     */
    public function build(Project $project, array $statistics): array
    {
        $s = $statistics;
        $framework = app(QuestionFramework::class);
        $rq = $project->researchQuestions->count();
        $ic = $project->inclusionCriterias->count();
        $ec = $project->exclusionCriterias->count();
        $ss = $project->searchStrings->count();

        // Project information
        $infoFields = ['description' => 'description', 'objetivo' => 'objective', 'research_time_interval' => 'time interval', 'area' => 'area', 'type_of_publication' => 'publication type'];
        $missingInfo = array_values(array_filter($infoFields, fn ($label, $field) => trim((string) $project->{$field}) === '' || $project->{$field} === 'Not provided', ARRAY_FILTER_USE_BOTH));
        $infoDone = trim((string) $project->description) !== '' && trim((string) $project->objetivo) !== '';

        // Research question framework: chosen, and every element described or not applicable
        $elements = $project->question_framework_elements ?? [];
        $pending = array_filter($elements, fn ($e) => empty($e['not_applicable']) && trim($e['description'] ?? '') === '');
        $frameworkName = $framework->name($project);
        $frameworkDone = $frameworkName !== null && $elements && !$pending;

        // Prompt templates: at least one per phase that uses them
        $byType = $project->templates()->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type');
        $templateCounts = [];
        foreach (['initial', 'final', 'extraction'] as $type) {
            $templateCounts[$type] = (int) ($byType[$type] ?? 0);
        }

        // PRISMA checklist answered (reported or not applicable)
        $answers = $project->prisma_checklist ?? [];
        $totalItems = count(PrismaChecklist::ITEMS);
        $answered = count(array_filter($answers, fn ($a) => in_array($a['status'] ?? '', ['reported', 'not_applicable'], true)));

        $phase = fn (array $p, string $what) => $p['total'] > 0
            ? ($p['null'] > 0 ? $p['null'] . ' ' . $what . ' without a decision' : 'All ' . $p['total'] . ' decided')
            : 'Nothing to assess yet';

        $dup = $s['duplicates_exclusion'];
        $dupTotal = $dup['yes'] + $dup['no'] + $dup['null'];

        return [
            ['title' => 'Planning', 'steps' => [
                $this->step('info', 'Project information', 'ri-file-text-line', $infoDone,
                    $missingInfo ? 'Missing: ' . implode(', ', $missingInfo) : 'Complete', '#project-info', true),
                $this->step('framework', 'Question framework', 'ri-node-tree', $frameworkDone,
                    $frameworkName === null ? 'No framework chosen'
                        : ($pending ? $frameworkName . ': ' . $this->count(count($pending), 'element', 'elements') . ' without description' : $frameworkName),
                    '#question-framework', true),
                $this->step('strings', 'Search strings', 'ri-search-line', $ss > 0, $this->count($ss, 'string', 'strings'), '#search-strings', true),
                $this->step('questions', 'Research questions', 'ri-question-line', $rq > 0, $this->count($rq, 'question', 'questions'), '#research-questions', true),
                $this->step('inclusion', 'Inclusion criteria', 'ri-checkbox-circle-line', $ic > 0, $this->count($ic, 'criterion', 'criteria'), '#inclusion-criteria', true),
                $this->step('exclusion', 'Exclusion criteria', 'ri-close-circle-line', $ec > 0, $this->count($ec, 'criterion', 'criteria'), '#exclusion-criteria', true),
                $this->step('templates', 'Prompt templates', 'ri-file-text-line', !in_array(0, $templateCounts, true),
                    'Initial ' . $templateCounts['initial'] . ' · Final ' . $templateCounts['final'] . ' · Extraction ' . $templateCounts['extraction'],
                    '#prompt-templates', true),
            ]],
            ['title' => 'Conducting', 'steps' => [
                $this->step('search', 'Search and import', 'ri-database-2-line', $s['total_studies'] > 0,
                    $s['total_studies'] . ' studies', route('projects.studies', $project), false),
                $this->step('duplicates', 'Duplicate removal', 'ri-file-copy-2-line', $dupTotal > 0 && $dup['null'] === 0,
                    $dupTotal > 0 ? ($dup['null'] > 0 ? $dup['null'] . ' studies not classified' : $dup['no'] . ' duplicates removed') : 'No studies yet',
                    route('projects.duplicates', $project), false),
                $this->step('initial', 'Initial selection', 'ri-filter-line',
                    $s['initial_selection']['total'] > 0 && $s['initial_selection']['null'] === 0,
                    $phase($s['initial_selection'], 'studies'), route('projects.initial', $project), false),
                $this->step('final', 'Final selection', 'ri-filter-2-line',
                    $s['final_selection']['total'] > 0 && $s['final_selection']['null'] === 0,
                    $phase($s['final_selection'], 'studies'), route('projects.final', $project), false),
                $this->step('extraction', 'Data extraction', 'ri-table-line', $s['extraction']['count'] > 0,
                    $this->count($s['extraction']['count'], 'extraction run', 'extraction runs'), route('projects.extraction', $project), false),
            ]],
            ['title' => 'Reporting', 'steps' => [
                $this->step('report', 'Report', 'ri-file-chart-line',
                    $s['report']['total'] > 0 && $s['report']['answered'] === $s['report']['total'],
                    $s['report']['answered'] . ' of ' . $s['report']['total'] . ' questions answered', route('projects.report', $project), false),
                $this->step('prisma', 'PRISMA 2020', 'ri-list-check-3', $answered === $totalItems,
                    $answered . ' of ' . $totalItems . ' checklist items answered', route('projects.prisma', $project), false),
            ]],
        ];
    }

    private function count(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }

    private function step(string $key, string $label, string $icon, bool $done, string $detail, string $href, bool $anchor): array
    {
        return compact('key', 'label', 'icon', 'done', 'detail', 'href', 'anchor');
    }
}

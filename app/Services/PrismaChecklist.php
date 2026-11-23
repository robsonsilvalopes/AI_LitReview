<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * PRISMA 2020 checklist (Page MJ, et al. BMJ 2021;372:n71, CC BY 4.0), with suggestions drawn
 * from what the project recorded in the tool (protocol, search strings, screenings, extractions,
 * flow numbers) for the items it can document.
 */
class PrismaChecklist
{
    public const STATUSES = [
        'reported' => 'Reported',
        'not_applicable' => 'Not applicable',
        'not_reported' => 'Not reported yet',
    ];

    /** [id, section, topic, item text]; a topic is shown on its first item only. */
    public const ITEMS = [
        ['1', 'TITLE', 'Title', 'Identify the report as a systematic review.'],
        ['2', 'ABSTRACT', 'Abstract', 'See the PRISMA 2020 for Abstracts checklist.'],
        ['3', 'INTRODUCTION', 'Rationale', 'Describe the rationale for the review in the context of existing knowledge.'],
        ['4', 'INTRODUCTION', 'Objectives', 'Provide an explicit statement of the objective(s) or question(s) the review addresses.'],
        ['5', 'METHODS', 'Eligibility criteria', 'Specify the inclusion and exclusion criteria for the review and how studies were grouped for the syntheses.'],
        ['6', 'METHODS', 'Information sources', 'Specify all databases, registers, websites, organisations, reference lists and other sources searched or consulted to identify studies. Specify the date when each source was last searched or consulted.'],
        ['7', 'METHODS', 'Search strategy', 'Present the full search strategies for all databases, registers and websites, including any filters and limits used.'],
        ['8', 'METHODS', 'Selection process', 'Specify the methods used to decide whether a study met the inclusion criteria of the review, including how many reviewers screened each record and each report retrieved, whether they worked independently, and if applicable, details of automation tools used in the process.'],
        ['9', 'METHODS', 'Data collection process', 'Specify the methods used to collect data from reports, including how many reviewers collected data from each report, whether they worked independently, any processes for obtaining or confirming data from study investigators, and if applicable, details of automation tools used in the process.'],
        ['10a', 'METHODS', 'Data items', 'List and define all outcomes for which data were sought. Specify whether all results that were compatible with each outcome domain in each study were sought (e.g. for all measures, time points, analyses), and if not, the methods used to decide which results to collect.'],
        ['10b', 'METHODS', 'Data items', 'List and define all other variables for which data were sought (e.g. participant and intervention characteristics, funding sources). Describe any assumptions made about any missing or unclear information.'],
        ['11', 'METHODS', 'Study risk of bias assessment', 'Specify the methods used to assess risk of bias in the included studies, including details of the tool(s) used, how many reviewers assessed each study and whether they worked independently, and if applicable, details of automation tools used in the process.'],
        ['12', 'METHODS', 'Effect measures', 'Specify for each outcome the effect measure(s) (e.g. risk ratio, mean difference) used in the synthesis or presentation of results.'],
        ['13a', 'METHODS', 'Synthesis methods', 'Describe the processes used to decide which studies were eligible for each synthesis (e.g. tabulating the study intervention characteristics and comparing against the planned groups for each synthesis (item #5)).'],
        ['13b', 'METHODS', 'Synthesis methods', 'Describe any methods required to prepare the data for presentation or synthesis, such as handling of missing summary statistics, or data conversions.'],
        ['13c', 'METHODS', 'Synthesis methods', 'Describe any methods used to tabulate or visually display results of individual studies and syntheses.'],
        ['13d', 'METHODS', 'Synthesis methods', 'Describe any methods used to synthesize results and provide a rationale for the choice(s). If meta-analysis was performed, describe the model(s), method(s) to identify the presence and extent of statistical heterogeneity, and software package(s) used.'],
        ['13e', 'METHODS', 'Synthesis methods', 'Describe any methods used to explore possible causes of heterogeneity among study results (e.g. subgroup analysis, meta-regression).'],
        ['13f', 'METHODS', 'Synthesis methods', 'Describe any sensitivity analyses conducted to assess robustness of the synthesized results.'],
        ['14', 'METHODS', 'Reporting bias assessment', 'Describe any methods used to assess risk of bias due to missing results in a synthesis (arising from reporting biases).'],
        ['15', 'METHODS', 'Certainty assessment', 'Describe any methods used to assess certainty (or confidence) in the body of evidence for an outcome.'],
        ['16a', 'RESULTS', 'Study selection', 'Describe the results of the search and selection process, from the number of records identified in the search to the number of studies included in the review, ideally using a flow diagram.'],
        ['16b', 'RESULTS', 'Study selection', 'Cite studies that might appear to meet the inclusion criteria, but which were excluded, and explain why they were excluded.'],
        ['17', 'RESULTS', 'Study characteristics', 'Cite each included study and present its characteristics.'],
        ['18', 'RESULTS', 'Risk of bias in studies', 'Present assessments of risk of bias for each included study.'],
        ['19', 'RESULTS', 'Results of individual studies', 'For all outcomes, present, for each study: (a) summary statistics for each group (where appropriate) and (b) an effect estimate and its precision (e.g. confidence/credible interval), ideally using structured tables or plots.'],
        ['20a', 'RESULTS', 'Results of syntheses', 'For each synthesis, briefly summarise the characteristics and risk of bias among contributing studies.'],
        ['20b', 'RESULTS', 'Results of syntheses', 'Present results of all statistical syntheses conducted. If meta-analysis was done, present for each the summary estimate and its precision (e.g. confidence/credible interval) and measures of statistical heterogeneity. If comparing groups, describe the direction of the effect.'],
        ['20c', 'RESULTS', 'Results of syntheses', 'Present results of all investigations of possible causes of heterogeneity among study results.'],
        ['20d', 'RESULTS', 'Results of syntheses', 'Present results of all sensitivity analyses conducted to assess the robustness of the synthesized results.'],
        ['21', 'RESULTS', 'Reporting biases', 'Present assessments of risk of bias due to missing results (arising from reporting biases) for each synthesis assessed.'],
        ['22', 'RESULTS', 'Certainty of evidence', 'Present assessments of certainty (or confidence) in the body of evidence for each outcome assessed.'],
        ['23a', 'DISCUSSION', 'Discussion', 'Provide a general interpretation of the results in the context of other evidence.'],
        ['23b', 'DISCUSSION', 'Discussion', 'Discuss any limitations of the evidence included in the review.'],
        ['23c', 'DISCUSSION', 'Discussion', 'Discuss any limitations of the review processes used.'],
        ['23d', 'DISCUSSION', 'Discussion', 'Discuss implications of the results for practice, policy, and future research.'],
        ['24a', 'OTHER INFORMATION', 'Registration and protocol', 'Provide registration information for the review, including register name and registration number, or state that the review was not registered.'],
        ['24b', 'OTHER INFORMATION', 'Registration and protocol', 'Indicate where the review protocol can be accessed, or state that a protocol was not prepared.'],
        ['24c', 'OTHER INFORMATION', 'Registration and protocol', 'Describe and explain any amendments to information provided at registration or in the protocol.'],
        ['25', 'OTHER INFORMATION', 'Support', 'Describe sources of financial or non-financial support for the review, and the role of the funders or sponsors in the review.'],
        ['26', 'OTHER INFORMATION', 'Competing interests', 'Declare any competing interests of review authors.'],
        ['27', 'OTHER INFORMATION', 'Availability of data, code and other materials', 'Report which of the following are publicly available and where they can be found: template data collection forms; data extracted from included studies; data used for all analyses; analytic code; any other materials used in the review.'],
    ];

    public static function itemIds(): array
    {
        return array_column(self::ITEMS, 0);
    }

    /**
     * The checklist rows with the project's answers.
     *
     * @return array<int, array{id: string, section: string, topic: string, text: string, show_topic: bool,
     *               status: string, location: string, notes: string, suggestion: ?string}>
     */
    public function rows(Project $project, array $flow): array
    {
        $answers = $project->prisma_checklist ?? [];
        $suggestions = $this->suggestions($project, $flow);

        $rows = [];
        $previousTopic = null;
        foreach (self::ITEMS as [$id, $section, $topic, $text]) {
            $answer = $answers[$id] ?? [];
            $rows[] = [
                'id' => $id,
                'section' => $section,
                'topic' => $topic,
                'text' => $text,
                'show_topic' => $topic !== $previousTopic,
                'status' => $answer['status'] ?? '',
                'location' => $answer['location'] ?? '',
                'notes' => $answer['notes'] ?? '',
                'suggestion' => $suggestions[$id] ?? null,
            ];
            $previousTopic = $topic;
        }

        return $rows;
    }

    /**
     * What the tool recorded for the items it can document, as plain text to adapt in the
     * manuscript.
     *
     * @return array<string, string>
     */
    public function suggestions(Project $project, array $flow): array
    {
        $project->loadMissing([
            'researchQuestions', 'inclusionCriterias', 'exclusionCriterias', 'searchStrings',
            'initialScreenings.aiModel', 'initialScreenings.responsavel',
            'finalScreenings.aiModel', 'finalScreenings.template', 'finalScreenings.responsavel',
            'extractions.aiModel', 'extractions.template', 'extractions.responsavel',
        ]);
        $s = [];

        // 4. Objectives
        $lines = [];
        if ($project->objetivo) {
            $lines[] = 'Objective: ' . trim($project->objetivo);
        }
        foreach ($project->researchQuestions as $i => $rq) {
            $lines[] = ($rq->custom_id ?: 'RQ' . ($i + 1)) . ': ' . trim($rq->research_question);
        }
        $frameworkText = app(QuestionFramework::class)->text($project);
        if ($frameworkText !== '') {
            $lines[] = $frameworkText;
        }
        if ($lines) {
            $s['4'] = implode("\n", $lines);
        }

        // 5. Eligibility criteria
        $lines = [];
        foreach ($project->inclusionCriterias as $c) {
            $lines[] = 'Inclusion ' . ($c->custom_id ? $c->custom_id . ': ' : '') . trim($c->inclusion_criteria);
        }
        foreach ($project->exclusionCriterias as $c) {
            $lines[] = 'Exclusion ' . ($c->custom_id ? $c->custom_id . ': ' : '') . trim($c->exclusion_criteria);
        }
        if ($project->research_time_interval) {
            $lines[] = 'Time interval: ' . $project->research_time_interval;
        }
        if ($project->type_of_publication) {
            $lines[] = 'Publication type: ' . $project->type_of_publication;
        }
        if ($project->area) {
            $lines[] = 'Area: ' . $project->area;
        }
        foreach (app(QuestionFramework::class)->filledElements($project) as $e) {
            if (empty($e['not_applicable']) && trim($e['description'] ?? '') !== '') {
                $lines[] = $e['label'] . ': ' . trim($e['description']);
            }
        }
        if ($lines) {
            $s['5'] = implode("\n", $lines);
        }

        // 6. Information sources, with the dates the records were imported
        if ($project->searchStrings->isNotEmpty()) {
            $dates = DB::table('studies')->where('project_id', $project->id)->whereNotNull('search_string_id')
                ->groupBy('search_string_id')
                ->get(['search_string_id', DB::raw('MIN(created_at) as first'), DB::raw('MAX(created_at) as last'), DB::raw('COUNT(*) as total')])
                ->keyBy('search_string_id');
            $lines = [];
            foreach ($project->searchStrings as $ss) {
                $d = $dates[$ss->id] ?? null;
                $lines[] = $ss->database
                    . ($ss->api_source ? ' (searched through its API)' : '')
                    . ($d ? ': ' . $d->total . ' records imported on ' . substr($d->last, 0, 10) : ': no records imported yet');
            }
            $lines[] = 'Note: the import date is when the records entered the tool; state the date each source was last searched.';
            $s['6'] = implode("\n", $lines);

            // 7. Search strategy
            $s['7'] = $project->searchStrings->map(fn ($ss) => $ss->database . ":\n" . trim($ss->string))->implode("\n\n");
        }

        // 8. Selection process, 9. Data collection process
        $describe = function ($runs, string $phase) {
            $lines = [];
            foreach ($runs as $run) {
                $who = $run->tipo === 'Human'
                    ? 'human reviewer' . ($run->responsavel ? ' (' . $run->responsavel->name . ')' : '')
                    : 'automation: large language model ' . ($run->aiModel?->name ?: 'not recorded') . ($run->tipo === 'AI Local' ? ' run locally (Ollama)' : ' via OpenRouter')
                        . ($run->template ? ', prompt template "' . $run->template->name . '"' : '');
                $lines[] = $phase . ' "' . $run->nome . '"' . ($run->data ? ' (' . \Illuminate\Support\Carbon::parse($run->data)->format('Y-m-d') . ')' : '') . ': ' . $who;
            }

            return $lines;
        };
        $selection = array_merge(
            $describe($project->initialScreenings, 'Title and abstract screening'),
            $describe($project->finalScreenings, 'Full-text screening')
        );
        if ($selection) {
            $s['8'] = implode("\n", $selection);
        }
        $collection = $describe($project->extractions, 'Data extraction');
        if ($collection) {
            $s['9'] = implode("\n", $collection);
        }

        // 10a. Data items: the research questions the extraction answers
        if ($project->researchQuestions->isNotEmpty() && $project->extractions->isNotEmpty()) {
            $s['10a'] = "Data extracted to answer the research questions:\n"
                . $project->researchQuestions->map(fn ($rq, $i) => ($rq->custom_id ?: 'RQ' . ($i + 1)) . ': ' . trim($rq->research_question))->implode("\n");
        }

        // 16a / 16b. Study selection
        $n = $flow['numbers'];
        $lines = ['Records identified: ' . $flow['identified'] . ' ('
            . implode('; ', array_map(fn ($db) => $db['name'] . ' ' . $db['count'], $flow['databases'])) . ')'];
        $lines[] = 'Duplicates removed: ' . $n['duplicates'];
        $lines[] = 'Records screened: ' . $n['screened'] . '; excluded: ' . $n['screening_excluded'];
        $lines[] = 'Reports sought: ' . $n['sought'] . '; not retrieved: ' . $n['not_retrieved'];
        $lines[] = 'Reports assessed: ' . $n['assessed'] . '; excluded: ' . $n['assessed_excluded'];
        $lines[] = 'Studies included: ' . $n['included'];
        $lines[] = 'See the PRISMA 2020 flow diagram (PRISMA page of the project).';
        $s['16a'] = implode("\n", $lines);
        if (!empty($flow['reasons'])) {
            $s['16b'] = "Reasons for excluding reports:\n" . implode("\n", array_map(fn ($r) => $r['label'] . ': ' . $r['count'], $flow['reasons']));
        }

        // 17. Study characteristics
        if ($n['included'] > 0) {
            $s['17'] = $n['included'] . ' studies included. Their list (title, DOI, abstract, link) can be exported from the Final Selection page (Download XLS).';
        }

        return $s;
    }

    /**
     * Keep only known items and fields, trimmed.
     */
    public function sanitize(array $input): array
    {
        $clean = [];
        foreach (self::itemIds() as $id) {
            $answer = $input[$id] ?? null;
            if (!is_array($answer)) {
                continue;
            }
            $status = in_array($answer['status'] ?? '', array_keys(self::STATUSES), true) ? $answer['status'] : '';
            $location = mb_substr(trim((string) ($answer['location'] ?? '')), 0, 500);
            $notes = mb_substr(trim((string) ($answer['notes'] ?? '')), 0, 5000);
            if ($status !== '' || $location !== '' || $notes !== '') {
                $clean[$id] = ['status' => $status, 'location' => $location, 'notes' => $notes];
            }
        }

        return $clean;
    }
}

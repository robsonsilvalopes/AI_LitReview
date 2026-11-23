<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Phase statistics of a project (progress cards, Sankey diagram, review steps menu), counted in
 * the database. A phase status is 1 (included), 0 (excluded) or null (no decision yet).
 */
class ProjectStatistics
{
    public function compute(Project $project): array
    {
        $studies = DB::table('studies')->where('project_id', $project->id);

        $row = (clone $studies)->selectRaw('
            COUNT(*) AS total,
            SUM(status_duplicados_exclusao = 1) AS dup_yes,
            SUM(status_duplicados_exclusao = 0) AS dup_no,
            SUM(status_duplicados_exclusao IS NULL) AS dup_null,
            SUM(status_duplicados_exclusao = 1 AND status_selecao_inicial = 1) AS ini_yes,
            SUM(status_duplicados_exclusao = 1 AND status_selecao_inicial = 0) AS ini_no,
            SUM(status_duplicados_exclusao = 1 AND status_selecao_inicial IS NULL) AS ini_null,
            SUM(status_selecao_inicial = 1) AS fin_total,
            SUM(status_selecao_inicial = 1 AND status_selecao_final = 1) AS fin_yes,
            SUM(status_selecao_inicial = 1 AND status_selecao_final = 0) AS fin_no,
            SUM(status_selecao_inicial = 1 AND status_selecao_final IS NULL) AS fin_null,
            SUM(status_extracao = 1) AS ext_yes,
            SUM(status_extracao = 0) AS ext_no,
            SUM(status_extracao IS NULL) AS ext_null,
            SUM(registration_method = \'Manual\') AS manual,
            SUM(registration_method = \'XLS/CSV\') AS xls
        ')->first();
        $n = fn (string $key) => (int) ($row->{$key} ?? 0);

        $total = $n('total');
        $initialTotal = $n('dup_yes');
        $finalTotal = $n('fin_total');
        $extractionsCount = $project->extractions()->count();
        $rqTotal = $project->researchQuestions()->count();
        $rqAnswered = $project->researchQuestions()->whereNotNull('answer')->where('answer', '!=', '')->count();

        $percent = fn (int $part, int $of) => $of > 0 ? round($part / $of * 100) : 0;

        return [
            'total_studies' => $total,
            'studies_by_method' => [
                'manual' => $n('manual'),
                'xls_csv' => $n('xls'),
                'other' => $total - $n('manual') - $n('xls'),
            ],
            'duplicates_exclusion' => [
                'yes' => $n('dup_yes'),
                'no' => $n('dup_no'),
                'null' => $n('dup_null'),
                'progress' => $percent($n('dup_yes') + $n('dup_no'), $total),
            ],
            'initial_selection' => [
                'total' => $initialTotal,
                'yes' => $n('ini_yes'),
                'no' => $n('ini_no'),
                'null' => $n('ini_null'),
                'progress' => $percent($n('ini_yes') + $n('ini_no'), $initialTotal),
            ],
            'final_selection' => [
                'total' => $finalTotal,
                'yes' => $n('fin_yes'),
                'no' => $n('fin_no'),
                'null' => $n('fin_null'),
                'progress' => $percent($n('fin_yes') + $n('fin_no'), $finalTotal),
            ],
            'extraction' => [
                'yes' => $n('ext_yes'),
                'no' => $n('ext_no'),
                'null' => $n('ext_null'),
                'count' => $extractionsCount,
                'progress' => $extractionsCount > 0 ? 100 : 0,
            ],
            'report' => [
                'answered' => $rqAnswered,
                'total' => $rqTotal,
                'progress' => $percent($rqAnswered, $rqTotal),
            ],
            'sankey' => [
                'total' => $total,
                'duplicates_excluded' => $n('dup_no'),
                'passed_to_initial' => $n('dup_yes'),
                'initial_excluded' => $n('ini_no'),
                'passed_to_final' => $n('ini_yes'),
                'final_excluded' => $n('fin_no'),
                'passed_to_extraction' => $n('fin_yes'),
            ],
        ];
    }
}

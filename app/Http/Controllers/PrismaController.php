<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\PrismaChecklist;
use App\Services\PrismaFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * PRISMA 2020 flow diagram and checklist of a project. Collaborators can view and export them;
 * only the owner saves adjustments and checklist answers.
 */
class PrismaController extends Controller
{
    public function __construct(private PrismaFlow $flow, private PrismaChecklist $checklist)
    {
    }

    private function hasAccess(Project $project): bool
    {
        return $project->user_id === Auth::id()
            || $project->collaborators()->where('user_id', Auth::id())->exists();
    }

    private function authorizeView(Project $project): void
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function authorizeOwner(Project $project): void
    {
        if ($project->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function show(Project $project)
    {
        $this->authorizeView($project);

        $flow = $this->flow->resolve($project);

        return view('content.projects.prisma', [
            'project' => $project,
            'flow' => $flow,
            'svg' => $this->flow->svg($flow),
            'adjustable' => PrismaFlow::ADJUSTABLE,
            'rows' => $this->checklist->rows($project, $flow),
            'statuses' => PrismaChecklist::STATUSES,
            'canEdit' => $project->user_id === Auth::id(),
        ]);
    }

    /**
     * Save the manual adjustments of the flow numbers (empty = use the computed value) and the
     * optional reasons typed by hand.
     */
    public function saveFlow(Request $request, Project $project)
    {
        $this->authorizeOwner($project);

        $rules = ['reasons_text' => 'nullable|string|max:5000', 'numbers' => 'nullable|array'];
        foreach (array_keys(PrismaFlow::ADJUSTABLE) as $key) {
            $rules['numbers.' . $key] = 'nullable|integer|min:0|max:100000000';
        }
        $validated = $request->validate($rules);

        $numbers = [];
        foreach (array_keys(PrismaFlow::ADJUSTABLE) as $key) {
            $value = $validated['numbers'][$key] ?? null;
            if ($value !== null && $value !== '') {
                $numbers[$key] = (int) $value;
            }
        }
        $project->prisma_flow = [
            'numbers' => $numbers,
            'reasons_text' => trim($validated['reasons_text'] ?? ''),
        ];
        $project->save();

        $flow = $this->flow->resolve($project);

        return response()->json([
            'success' => true,
            'message' => 'Flow diagram updated.',
            'svg' => $this->flow->svg($flow),
            'numbers' => $flow['numbers'],
        ]);
    }

    public function saveChecklist(Request $request, Project $project)
    {
        $this->authorizeOwner($project);

        $request->validate(['items' => 'required|array']);
        $project->prisma_checklist = $this->checklist->sanitize($request->input('items'));
        $project->save();

        return response()->json(['success' => true, 'message' => 'Checklist saved.']);
    }

    public function flowSvg(Project $project)
    {
        $this->authorizeView($project);

        $svg = $this->flow->svg($this->flow->resolve($project), $project->name);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->filename($project, 'prisma-flow') . '.svg"',
        ]);
    }

    public function checklistXlsx(Project $project)
    {
        $this->authorizeView($project);

        $rows = $this->checklist->rows($project, $this->flow->resolve($project));
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PRISMA 2020 checklist');

        $sheet->setCellValue('A1', 'PRISMA 2020 checklist — ' . $project->name);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $headers = ['Section and Topic', 'Item #', 'Checklist item', 'Status', 'Location where item is reported', 'Notes', 'Recorded in the tool'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 3], $header);
        }
        $sheet->getStyle('A3:G3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        ]);

        $r = 4;
        $section = null;
        foreach ($rows as $row) {
            if ($row['section'] !== $section) {
                $section = $row['section'];
                $sheet->setCellValue([1, $r], $section);
                $sheet->mergeCells([1, $r, 7, $r]);
                $sheet->getStyle([1, $r, 7, $r])->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                ]);
                $r++;
            }
            $sheet->setCellValue([1, $r], $row['show_topic'] ? $row['topic'] : '');
            $sheet->setCellValueExplicit([2, $r], $row['id'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([3, $r], $row['text']);
            $sheet->setCellValue([4, $r], PrismaChecklist::STATUSES[$row['status']] ?? '');
            $sheet->setCellValue([5, $r], $row['location']);
            $sheet->setCellValue([6, $r], $row['notes']);
            $sheet->setCellValue([7, $r], $row['suggestion'] ?? '');
            $r++;
        }

        foreach (['A' => 24, 'B' => 8, 'C' => 70, 'D' => 16, 'E' => 30, 'F' => 40, 'G' => 60] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getStyle('A4:G' . ($r - 1))->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
        $r++;
        $sheet->setCellValue([1, $r], 'From: Page MJ, McKenzie JE, Bossuyt PM, et al. The PRISMA 2020 statement: an updated guideline for reporting systematic reviews. BMJ 2021;372:n71. doi: 10.1136/bmj.n71. Licensed under CC BY 4.0.');
        $sheet->mergeCells([1, $r, 7, $r]);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $this->filename($project, 'prisma-checklist') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function filename(Project $project, string $prefix): string
    {
        return $prefix . '-' . (Str::slug($project->name) ?: 'project-' . $project->id);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\QuestionFramework;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Research question framework (PICO, PICOC, PCC, SPIDER...) of a project: the owner saves it;
 * owner and collaborators can generate the search string drafts.
 */
class QuestionFrameworkController extends Controller
{
    public function __construct(private QuestionFramework $framework)
    {
    }

    public function update(Request $request, Project $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'framework' => ['nullable', Rule::in(array_keys(QuestionFramework::FRAMEWORKS))],
            'elements' => 'nullable|array|max:12',
            'date_limit' => 'boolean',
            'date_from' => 'nullable|date_format:Y-m-d|required_if_accepted:date_limit',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from|required_if_accepted:date_limit',
        ], [
            'date_from.required_if_accepted' => 'Enter the start date of the search date range.',
            'date_to.required_if_accepted' => 'Enter the end date of the search date range.',
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        $framework = $validated['framework'] ?? null;
        $project->question_framework = $framework;
        $project->question_framework_elements = $framework
            ? $this->framework->sanitize($framework, $validated['elements'] ?? [])
            : null;
        // The dates are kept when the limit is turned off, so turning it on again restores them
        $project->search_date_limit = (bool) ($validated['date_limit'] ?? false);
        if (array_key_exists('date_from', $validated)) {
            $project->search_date_from = $validated['date_from'];
        }
        if (array_key_exists('date_to', $validated)) {
            $project->search_date_to = $validated['date_to'];
        }
        $project->save();

        return response()->json([
            'success' => true,
            'message' => $framework ? 'Research question framework saved.' : 'Research question framework removed.',
            'framework' => $project->question_framework,
            'elements' => $project->question_framework_elements,
            'date_limit' => $project->search_date_limit,
            'date_from' => $project->search_date_from?->format('Y-m-d'),
            'date_to' => $project->search_date_to?->format('Y-m-d'),
        ]);
    }

    public function drafts(Project $project)
    {
        $allowed = $project->user_id === Auth::id()
            || $project->collaborators()->where('user_id', Auth::id())->exists();
        if (!$allowed) {
            abort(403, 'Unauthorized action.');
        }

        return response()->json($this->framework->searchDrafts($project) + [
            'syntaxes' => QuestionFramework::SYNTAXES,
        ]);
    }
}

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
        ]);

        $framework = $validated['framework'] ?? null;
        $project->question_framework = $framework;
        $project->question_framework_elements = $framework
            ? $this->framework->sanitize($framework, $validated['elements'] ?? [])
            : null;
        $project->save();

        return response()->json([
            'success' => true,
            'message' => $framework ? 'Research question framework saved.' : 'Research question framework removed.',
            'framework' => $project->question_framework,
            'elements' => $project->question_framework_elements,
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

<?php

namespace App\Http\Controllers;

use App\Models\ResearchQuestion;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ResearchQuestionController extends Controller
{
    /**
     * Check if the authenticated user has access to the project (owner or collaborator)
     */
    private function hasAccess(Project $project): bool
    {
        $userId = Auth::id();
        if ($project->user_id === $userId) {
            return true;
        }
        return $project->collaborators()->where('user_id', $userId)->exists();
    }

    /**
     * Check if the authenticated user is the owner of the project
     */
    private function isOwner(Project $project): bool
    {
        return $project->user_id === Auth::id();
    }

    /**
     * Display a listing of research questions for a project.
     */
    public function index(Project $project)
    {
        // Collaborators can view research questions
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        $researchQuestions = $project->researchQuestions()->orderBy('id')->get();
        return response()->json($researchQuestions);
    }

    /**
     * Store a newly created research question.
     */
    public function store(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:30',
            'research_question' => 'required|string',
        ]);

        try {
            // Generate custom_id if not provided
            if (empty($validated['custom_id'])) {
                $validated['custom_id'] = ResearchQuestion::generateNextCustomId($project->id);
            }

            $researchQuestion = $project->researchQuestions()->create($validated);
            $researchQuestion->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Research question created successfully',
                'research_question' => $researchQuestion
            ]);
        } catch (\Exception $e) {
            Log::error('ResearchQuestionController@store Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating research question. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified research question.
     */
    public function update(Request $request, Project $project, ResearchQuestion $researchQuestion)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the research question belongs to the project
        if ($researchQuestion->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        // Allow updating only answer, or full question update
        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:30',
            'research_question' => 'sometimes|required|string',
            'answer' => 'nullable|string',
        ]);
        
        // If only answer is provided, don't require research_question
        if ($request->has('answer') && !$request->has('research_question')) {
            $validated = ['answer' => $request->input('answer')];
        }

        try {
            $researchQuestion->update($validated);
            $researchQuestion->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Research question updated successfully',
                'research_question' => $researchQuestion
            ]);
        } catch (\Exception $e) {
            Log::error('ResearchQuestionController@update Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating research question. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified research question.
     */
    public function destroy(Project $project, ResearchQuestion $researchQuestion)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the research question belongs to the project
        if ($researchQuestion->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $researchQuestion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Research question deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('ResearchQuestionController@destroy Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting research question. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

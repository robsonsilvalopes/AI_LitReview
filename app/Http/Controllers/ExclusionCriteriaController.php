<?php

namespace App\Http\Controllers;

use App\Models\ExclusionCriteria;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ExclusionCriteriaController extends Controller
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
     * Display a listing of exclusion criterias for a project.
     */
    public function index(Project $project)
    {
        // Collaborators can view exclusion criteria
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        $exclusionCriterias = $project->exclusionCriterias()->orderBy('id')->get();
        return response()->json($exclusionCriterias);
    }

    /**
     * Store a newly created exclusion criteria.
     */
    public function store(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:30',
            'exclusion_criteria' => 'required|string',
        ]);

        try {
            // Generate custom_id if not provided
            if (empty($validated['custom_id'])) {
                $validated['custom_id'] = ExclusionCriteria::generateNextCustomId($project->id);
            }

            $exclusionCriteria = $project->exclusionCriterias()->create($validated);
            $exclusionCriteria->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Exclusion criteria created successfully',
                'exclusion_criteria' => $exclusionCriteria
            ]);
        } catch (\Exception $e) {
            Log::error('ExclusionCriteriaController@store Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating exclusion criteria. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified exclusion criteria.
     */
    public function update(Request $request, Project $project, ExclusionCriteria $exclusionCriteria)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the exclusion criteria belongs to the project
        if ($exclusionCriteria->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:30',
            'exclusion_criteria' => 'required|string',
        ]);

        try {
            $exclusionCriteria->update($validated);
            $exclusionCriteria->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Exclusion criteria updated successfully',
                'exclusion_criteria' => $exclusionCriteria
            ]);
        } catch (\Exception $e) {
            Log::error('ExclusionCriteriaController@update Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating exclusion criteria. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified exclusion criteria.
     */
    public function destroy(Project $project, ExclusionCriteria $exclusionCriteria)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the exclusion criteria belongs to the project
        if ($exclusionCriteria->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $exclusionCriteria->delete();

            return response()->json([
                'success' => true,
                'message' => 'Exclusion criteria deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('ExclusionCriteriaController@destroy Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting exclusion criteria. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\InclusionCriteria;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InclusionCriteriaController extends Controller
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
     * Display a listing of inclusion criterias for a project.
     */
    public function index(Project $project)
    {
        // Collaborators can view inclusion criteria
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        $inclusionCriterias = $project->inclusionCriterias()->orderBy('id')->get();
        return response()->json($inclusionCriterias);
    }

    /**
     * Store a newly created inclusion criteria.
     */
    public function store(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:30',
            'inclusion_criteria' => 'required|string',
        ]);

        try {
            // Generate custom_id if not provided
            if (empty($validated['custom_id'])) {
                $validated['custom_id'] = InclusionCriteria::generateNextCustomId($project->id);
            }

            $inclusionCriteria = $project->inclusionCriterias()->create($validated);
            $inclusionCriteria->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Inclusion criteria created successfully',
                'inclusion_criteria' => $inclusionCriteria
            ]);
        } catch (\Exception $e) {
            Log::error('InclusionCriteriaController@store Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating inclusion criteria. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified inclusion criteria.
     */
    public function update(Request $request, Project $project, InclusionCriteria $inclusionCriteria)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the inclusion criteria belongs to the project
        if ($inclusionCriteria->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:30',
            'inclusion_criteria' => 'required|string',
        ]);

        try {
            $inclusionCriteria->update($validated);
            $inclusionCriteria->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Inclusion criteria updated successfully',
                'inclusion_criteria' => $inclusionCriteria
            ]);
        } catch (\Exception $e) {
            Log::error('InclusionCriteriaController@update Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating inclusion criteria. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified inclusion criteria.
     */
    public function destroy(Project $project, InclusionCriteria $inclusionCriteria)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the inclusion criteria belongs to the project
        if ($inclusionCriteria->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $inclusionCriteria->delete();

            return response()->json([
                'success' => true,
                'message' => 'Inclusion criteria deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('InclusionCriteriaController@destroy Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting inclusion criteria. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

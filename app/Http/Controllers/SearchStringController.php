<?php

namespace App\Http\Controllers;

use App\Models\SearchString;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SearchStringController extends Controller
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
     * Display a listing of search strings for a project.
     */
    public function index(Project $project)
    {
        // Collaborators can view search strings
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        $searchStrings = $project->searchStrings()->orderBy('id')->get();
        return response()->json($searchStrings);
    }

    /**
     * Store a newly created search string.
     */
    public function store(Request $request, Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'database' => 'required|string|max:250',
            'api_source' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(SearchString::API_SOURCES))],
            'string' => 'required|string',
        ]);

        try {
            $searchString = $project->searchStrings()->create($validated);
            $searchString->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Search string created successfully',
                'search_string' => $searchString
            ]);
        } catch (\Exception $e) {
            Log::error('SearchStringController@store Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating search string. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified search string.
     */
    public function update(Request $request, Project $project, SearchString $searchString)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the search string belongs to the project
        if ($searchString->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'database' => 'required|string|max:250',
            'api_source' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(SearchString::API_SOURCES))],
            'string' => 'required|string',
        ]);

        try {
            $searchString->update($validated);
            $searchString->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Search string updated successfully',
                'search_string' => $searchString
            ]);
        } catch (\Exception $e) {
            Log::error('SearchStringController@update Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating search string. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified search string.
     */
    public function destroy(Project $project, SearchString $searchString)
    {
        // Ensure the project belongs to the authenticated user
        if (!$this->isOwner($project)) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the search string belongs to the project
        if ($searchString->project_id !== $project->id) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $searchString->delete();

            return response()->json([
                'success' => true,
                'message' => 'Search string deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('SearchStringController@destroy Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting search string. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

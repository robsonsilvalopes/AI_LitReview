<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Check if the authenticated user has access to the project (owner or collaborator)
     */
    private function hasAccess(Project $project): bool
    {
        $userId = Auth::id();
        
        // Check if user is the owner
        if ($project->user_id === $userId) {
            return true;
        }
        
        // Check if user is a collaborator
        return $project->collaborators()->where('user_id', $userId)->exists();
    }

    /**
     * Check if the authenticated user is the owner of the project
     */
    private function isOwner(Project $project): bool
    {
        return $project->user_id === Auth::id();
    }

    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            $user = Auth::user();
            $projects = Project::where('user_id', $user->id)
                ->orWhereHas('collaborators', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orderBy('name', 'asc')
                ->get(['id', 'name']);
            
            return response()->json($projects);
        }
        
        // This route is not used, but kept for consistency
        return redirect()->route('home');
    }

    public function show(Project $project)
    {
        // Ensure user has access to the project (owner or collaborator)
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized access to this project.');
        }
        
        // Research questions, search strings and criteria are listed on the page; studies are loaded via AJAX
        $project->load('researchQuestions', 'searchStrings', 'inclusionCriterias', 'exclusionCriterias');

        // Phase statistics for the progress cards, the Sankey diagram and the review steps menu
        $statistics = app(\App\Services\ProjectStatistics::class)->compute($project);

        $steps = app(\App\Services\ProjectSteps::class)->build($project, $statistics);

        return view('content.projects.show', compact('project', 'statistics', 'steps'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'objetivo' => 'nullable|string',
                'research_time_interval' => 'nullable|string|max:255',
                'area' => 'nullable|string|max:255',
                'type_of_publication' => 'nullable|string|max:255',
            ]);

            // Associate project with authenticated user
            $validated['user_id'] = Auth::id();
            $project = Project::create($validated);
            $project->refresh(); // Ensure we have the latest data including ID

            // Add project owner as collaborator automatically
            $project->collaborators()->syncWithoutDetaching([Auth::id()]);

            // Clear menu cache for the current user
            Cache::forget('menu_data_user_' . Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'objetivo' => $project->objetivo,
                    'research_time_interval' => $project->research_time_interval,
                    'area' => $project->area,
                    'type_of_publication' => $project->type_of_publication,
                ]
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error: Unable to create project. Please check database connection.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating project. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function update(Request $request, Project $project)
    {
        // Only the owner can update the project
        if (!$this->isOwner($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'objetivo' => 'nullable|string',
            'research_time_interval' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'type_of_publication' => 'nullable|string|max:255',
        ]);

        $project->update($validated);
        $project->refresh();

        // Clear menu cache for the current user
        Cache::forget('menu_data_user_' . Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'objetivo' => $project->objetivo,
                'research_time_interval' => $project->research_time_interval,
                'area' => $project->area,
                'type_of_publication' => $project->type_of_publication,
            ]
        ]);
    }

    public function destroy(Request $request, Project $project)
    {
        // Only the owner can delete the project
        if (!$this->isOwner($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        $validated = $request->validate([
            'confirmation_name' => 'required|string',
        ]);

        if ($validated['confirmation_name'] !== $project->name) {
            return response()->json([
                'success' => false,
                'message' => 'Project name does not match'
            ], 422);
        }

        $project->delete();

        // Clear menu cache for the current user
        Cache::forget('menu_data_user_' . Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully'
        ]);
    }

    public function studies(Project $project)
    {
        // Ensure user has access to the project (owner or collaborator)
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized access to this project.');
        }
        
        // Load all studies for this project
        $project->load('studies');

        // Databases registered with the search strings, offered as the origin of BibTeX/RIS imports
        $searchStrings = $project->searchStrings()->orderBy('database')->get();

        $studyDatabases = $project->studyDatabases();

        return view('content.projects.studies', compact('project', 'searchStrings', 'studyDatabases'));
    }

    /**
     * Get collaborators for a project
     */
    public function getCollaborators(Project $project)
    {
        // Ensure user has access to the project (owner or collaborator)
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        // Get all collaborators
        $collaborators = $project->collaborators()->get();
        
        // Get project owner
        $owner = $project->user;
        
        // Combine owner and collaborators, ensuring owner is first
        $allUsers = collect([$owner])->merge($collaborators)->unique('id')->map(function ($user) use ($project) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_owner' => $user->id === $project->user_id,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $allUsers->values()
        ]);
    }

    /**
     * Add a collaborator to a project
     */
    public function addCollaborator(Request $request, Project $project)
    {
        // Only the owner can add collaborators
        if (!$this->isOwner($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        // Find user by email
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User with this email not found in the system.'
            ], 404);
        }

        // Don't allow adding the project owner as a collaborator (they're already the owner)
        if ($user->id === $project->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'The project owner cannot be added as a collaborator.'
            ], 422);
        }

        // Check if user is already a collaborator
        if ($project->collaborators()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a collaborator on this project.'
            ], 422);
        }

        // Add collaborator
        $project->collaborators()->attach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Collaborator added successfully.',
            'collaborator' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_owner' => false,
            ]
        ]);
    }

    /**
     * Remove a collaborator from a project
     */
    public function removeCollaborator(Request $request, Project $project, User $user)
    {
        // Only the owner can remove collaborators
        if (!$this->isOwner($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this project.'
            ], 403);
        }

        // Don't allow removing the project owner
        if ($user->id === $project->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the project owner.'
            ], 422);
        }

        // Check if user is a collaborator
        if (!$project->collaborators()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This user is not a collaborator on this project.'
            ], 404);
        }

        // Remove collaborator
        $project->collaborators()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Collaborator removed successfully.'
        ]);
    }
}

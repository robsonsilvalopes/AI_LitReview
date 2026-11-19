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
        
        // Load research questions, search strings, inclusion criterias, and exclusion criterias with the project
        // Studies are loaded via AJAX with pagination, but we need all studies for statistics
        $project->load('researchQuestions', 'searchStrings', 'inclusionCriterias', 'exclusionCriterias', 'studies', 'extractions');
        
        // Calculate statistics for the cards
        $totalStudies = $project->studies->count();
        
        // Count studies by registration method
        $studiesByMethod = $project->studies->groupBy('registration_method')->map->count();
        $manualCount = $studiesByMethod->get('Manual', 0);
        $xlsCsvCount = $studiesByMethod->get('XLS/CSV', 0);
        $otherCount = $totalStudies - $manualCount - $xlsCsvCount;
        
        // Count duplicates_exclusion: need to check raw values to avoid boolean cast issues
        // Use getRawOriginal to get the actual database value before casting
        $duplicatesExclusionYes = $project->studies->filter(function($study) {
            $raw = $study->getRawOriginal('status_duplicados_exclusao');
            return $raw === 1 || $raw === '1' || $raw === true;
        })->count();
        $duplicatesExclusionNo = $project->studies->filter(function($study) {
            $raw = $study->getRawOriginal('status_duplicados_exclusao');
            return ($raw === 0 || $raw === '0') && $raw !== null;
        })->count();
        $duplicatesExclusionNull = $project->studies->filter(function($study) {
            $raw = $study->getRawOriginal('status_duplicados_exclusao');
            return $raw === null;
        })->count();
        // Count initial_selection: only consider studies included in duplicates phase (status_duplicados_exclusao = 1)
        $studiesForInitialSelection = $project->studies->filter(function($study) {
            $raw = $study->getRawOriginal('status_duplicados_exclusao');
            return $raw === 1 || $raw === '1' || $raw === true;
        });
        $initialSelectionTotal = $studiesForInitialSelection->count();
        $initialSelectionYes = $studiesForInitialSelection->filter(function($study) {
            $raw = $study->getRawOriginal('status_selecao_inicial');
            return $raw === 1 || $raw === '1' || $raw === true;
        })->count();
        $initialSelectionNo = $studiesForInitialSelection->filter(function($study) {
            $raw = $study->getRawOriginal('status_selecao_inicial');
            return ($raw === 0 || $raw === '0') && $raw !== null;
        })->count();
        $initialSelectionNull = $studiesForInitialSelection->filter(function($study) {
            $raw = $study->getRawOriginal('status_selecao_inicial');
            return $raw === null;
        })->count();
        // Count final_selection: only consider studies included in initial selection (status_selecao_inicial = 1)
        $studiesForFinalSelection = $project->studies->filter(function($study) {
            $raw = $study->getRawOriginal('status_selecao_inicial');
            return $raw === 1 || $raw === '1' || $raw === true;
        });
        $finalSelectionTotal = $studiesForFinalSelection->count();
        $finalSelectionYes = $studiesForFinalSelection->filter(function($study) {
            $raw = $study->getRawOriginal('status_selecao_final');
            return $raw === 1 || $raw === '1' || $raw === true;
        })->count();
        $finalSelectionNo = $studiesForFinalSelection->filter(function($study) {
            $raw = $study->getRawOriginal('status_selecao_final');
            return ($raw === 0 || $raw === '0') && $raw !== null;
        })->count();
        $finalSelectionNull = $studiesForFinalSelection->filter(function($study) {
            $raw = $study->getRawOriginal('status_selecao_final');
            return $raw === null;
        })->count();
        $extractionYes = $project->studies->where('status_extracao', true)->count();
        $extractionNo = $project->studies->where('status_extracao', false)->count();
        $extractionNull = $project->studies->whereNull('status_extracao')->count();
        
        // Count extractions (from extractions table)
        $extractionsCount = $project->extractions()->count();
        
        // Calculate percentages for progress bars
        // Card 2: Progress = (studies classified, i.e., not null) / total studies * 100
        $duplicatesProgress = $totalStudies > 0 ? round((($duplicatesExclusionYes + $duplicatesExclusionNo) / $totalStudies) * 100) : 0;
        // Card 3: Progress = (studies classified, i.e., not null) / studies included in duplicates phase * 100
        $initialSelectionProgress = $initialSelectionTotal > 0 ? round((($initialSelectionYes + $initialSelectionNo) / $initialSelectionTotal) * 100) : 0;
        // Card 4: Progress = (studies classified, i.e., not null) / studies with status_selecao_inicial = 1 * 100
        $finalSelectionProgress = $finalSelectionTotal > 0 ? round((($finalSelectionYes + $finalSelectionNo) / $finalSelectionTotal) * 100) : 0;
        // Card 5: Extraction progress = 100% if at least 1 extraction exists, otherwise 0%
        $extractionProgress = $extractionsCount > 0 ? 100 : 0;
        
        // Card 6: Report progress = answered research questions / total research questions * 100
        $totalResearchQuestions = $project->researchQuestions()->count();
        $answeredResearchQuestions = $project->researchQuestions()->whereNotNull('answer')->where('answer', '!=', '')->count();
        $reportProgress = $totalResearchQuestions > 0 ? round(($answeredResearchQuestions / $totalResearchQuestions) * 100) : 0;
        
        // Sankey Diagram Data
        // Level 1: Total studies
        $sankeyTotal = $totalStudies;
        
        // Level 2: Excluded by duplicates vs Passed to initial selection
        $sankeyDuplicatesExcluded = $duplicatesExclusionNo;
        $sankeyPassedToInitial = $duplicatesExclusionYes; // Studies that passed duplicates (included)
        
        // Level 3: Excluded by initial selection vs Passed to final selection
        $sankeyInitialExcluded = $initialSelectionNo;
        $sankeyPassedToFinal = $initialSelectionYes; // Studies that passed initial selection (included)
        
        // Level 4: Excluded by final selection vs Passed to extraction
        $sankeyFinalExcluded = $finalSelectionNo;
        $sankeyPassedToExtraction = $finalSelectionYes; // Studies that passed final selection (included)
        
        $statistics = [
            'total_studies' => $totalStudies,
            'studies_by_method' => [
                'manual' => $manualCount,
                'xls_csv' => $xlsCsvCount,
                'other' => $otherCount
            ],
            'duplicates_exclusion' => [
                'yes' => $duplicatesExclusionYes,
                'no' => $duplicatesExclusionNo,
                'null' => $duplicatesExclusionNull,
                'progress' => $duplicatesProgress
            ],
            'initial_selection' => [
                'total' => $initialSelectionTotal,
                'yes' => $initialSelectionYes,
                'no' => $initialSelectionNo,
                'null' => $initialSelectionNull,
                'progress' => $initialSelectionProgress
            ],
            'final_selection' => [
                'total' => $finalSelectionTotal,
                'yes' => $finalSelectionYes,
                'no' => $finalSelectionNo,
                'null' => $finalSelectionNull,
                'progress' => $finalSelectionProgress
            ],
            'extraction' => [
                'yes' => $extractionYes,
                'no' => $extractionNo,
                'null' => $extractionNull,
                'count' => $extractionsCount,
                'progress' => $extractionProgress
            ],
            'report' => [
                'answered' => $answeredResearchQuestions,
                'total' => $totalResearchQuestions,
                'progress' => $reportProgress
            ],
            'sankey' => [
                'total' => $sankeyTotal,
                'duplicates_excluded' => $sankeyDuplicatesExcluded,
                'passed_to_initial' => $sankeyPassedToInitial,
                'initial_excluded' => $sankeyInitialExcluded,
                'passed_to_final' => $sankeyPassedToFinal,
                'final_excluded' => $sankeyFinalExcluded,
                'passed_to_extraction' => $sankeyPassedToExtraction
            ]
        ];
        
        return view('content.projects.show', compact('project', 'statistics'));
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

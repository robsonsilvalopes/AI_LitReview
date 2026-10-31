<?php

namespace App\Http\Controllers;

use App\Models\ExtractionData;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExtractionDataController extends Controller
{
    /**
     * Display a listing of extraction data for DataTables.
     */
    public function index(Request $request, Project $project)
    {
        // Ensure user has access to the project
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax() || $request->wantsJson()) {
            $draw = (int) $request->get('draw', 1);
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 15);
            
            $searchValue = '';
            if ($request->has('search') && is_array($request->get('search'))) {
                $searchValue = $request->get('search')['value'] ?? '';
            } elseif ($request->has('search')) {
                $searchValue = $request->get('search');
            }

            // Filter by project
            $query = ExtractionData::where('project_id', $project->id);

            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('custom_id', 'like', '%' . $searchValue . '%')
                      ->orWhere('name', 'like', '%' . $searchValue . '%')
                      ->orWhere('value', 'like', '%' . $searchValue . '%')
                      ->orWhere('note', 'like', '%' . $searchValue . '%');
                });
            }

            $totalRecords = ExtractionData::where('project_id', $project->id)->count();
            $filteredRecords = $query->count();

            $extractionData = $query->orderBy('custom_id', 'asc')
                ->skip($start)
                ->take($length)
                ->get();

            $data = $extractionData->map(function ($item) {
                return [
                    'id' => $item->id,
                    'custom_id' => $item->custom_id ?? 'N/A',
                    'name' => $item->name,
                    'value' => $item->value ?? '',
                    'note' => $item->note ?? '',
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }

    /**
     * Store a newly created extraction data.
     */
    public function store(Request $request, Project $project)
    {
        // Ensure user has access to the project
        if (!$this->hasAccess($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'value' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        // Generate custom_id if not provided
        if (empty($request->custom_id)) {
            $validated['custom_id'] = ExtractionData::generateNextCustomId($project->id);
        } else {
            $validated['custom_id'] = $request->custom_id;
        }

        $validated['project_id'] = $project->id;
        $extractionData = ExtractionData::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Extraction data created successfully.',
            'data' => $extractionData,
        ]);
    }

    /**
     * Update the specified extraction data.
     */
    public function update(Request $request, Project $project, ExtractionData $extractionData)
    {
        // Ensure user has access to the project and extraction data belongs to project
        if (!$this->hasAccess($project) || $extractionData->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validated = $request->validate([
            'custom_id' => 'nullable|string|max:20',
            'name' => 'required|string|max:255',
            'value' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $extractionData->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Extraction data updated successfully.',
            'data' => $extractionData,
        ]);
    }

    /**
     * Remove the specified extraction data.
     */
    public function destroy(Project $project, ExtractionData $extractionData)
    {
        // Ensure user has access to the project and extraction data belongs to project
        if (!$this->hasAccess($project) || $extractionData->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $extractionData->delete();

        return response()->json([
            'success' => true,
            'message' => 'Extraction data deleted successfully.',
        ]);
    }

    /**
     * Check if user has access to the project (owner or collaborator)
     */
    private function hasAccess(Project $project): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Owner has access
        if ($project->user_id === $user->id) {
            return true;
        }

        // Check if user is a collaborator
        return $project->collaborators()->where('user_id', $user->id)->exists();
    }
}

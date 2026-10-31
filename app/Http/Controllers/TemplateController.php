<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemplateController extends Controller
{
    /**
     * Display a listing of templates.
     */
    public function index(Request $request)
    {
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

            // Get type filter (initial or final)
            $type = $request->get('type', 'initial');

            // Filter templates by authenticated user and type
            $query = Template::where('user_id', Auth::id())
                ->where('type', $type);

            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('name', 'like', '%' . $searchValue . '%')
                      ->orWhere('template', 'like', '%' . $searchValue . '%');
                });
            }

            $totalRecords = Template::where('user_id', Auth::id())
                ->where('type', $type)
                ->count();
            $filteredRecords = $query->count();

            $templates = $query->orderBy('created_at', 'desc')
                ->skip($start)
                ->take($length)
                ->get();

            $data = $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'template' => $template->template,
                    'type' => $template->type,
                    'created_at' => $template->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $template->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        }

        return view('content.pages.templates');
    }

    /**
     * Store a newly created template.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template' => 'required|string',
            'type' => 'required|in:initial,final,extraction',
        ]);

        // Associate template with authenticated user
        $validated['user_id'] = Auth::id();
        $template = Template::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully.',
            'data' => $template,
        ]);
    }

    /**
     * Update the specified template.
     */
    public function update(Request $request, Template $template)
    {
        // Ensure user owns the template
        if ($template->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this template.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template' => 'required|string',
            'type' => 'required|in:initial,final,extraction',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully.',
            'data' => $template,
        ]);
    }

    /**
     * Remove the specified template.
     */
    public function destroy(Template $template)
    {
        // Ensure user owns the template
        if ($template->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this template.'
            ], 403);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully.',
        ]);
    }

}

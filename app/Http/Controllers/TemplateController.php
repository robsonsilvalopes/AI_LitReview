<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Prompt templates of a project (initial selection, final selection, data extraction). The owner
 * creates, edits, deletes and copies templates; collaborators view them.
 */
class TemplateController extends Controller
{
    private function hasAccess(Project $project): bool
    {
        return $project->user_id === Auth::id()
            || $project->collaborators()->where('user_id', Auth::id())->exists();
    }

    private function isOwner(Project $project): bool
    {
        return $project->user_id === Auth::id();
    }

    private function forbidden(string $message = 'Unauthorized access to this project.')
    {
        return response()->json(['success' => false, 'message' => $message], 403);
    }

    /**
     * One type's templates for the DataTable of the project page's Prompt Templates card
     * (a browser visit goes to that card).
     */
    public function index(Request $request, Project $project)
    {
        if (!$this->hasAccess($project)) {
            abort(403, 'Unauthorized action.');
        }

        // The templates are managed in their card on the project page
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->to(route('projects.show', $project) . '#prompt-templates');
        }

        $draw = (int) $request->get('draw', 1);
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 15);
        $searchValue = is_array($request->get('search')) ? ($request->get('search')['value'] ?? '') : (string) $request->get('search', '');
        $type = in_array($request->get('type'), ['initial', 'final', 'extraction'], true) ? $request->get('type') : 'initial';

        $base = $project->templates()->where('type', $type);
        $query = (clone $base)->when($searchValue !== '', fn ($q) => $q->where(fn ($w) => $w
            ->where('name', 'like', '%' . $searchValue . '%')
            ->orWhere('template', 'like', '%' . $searchValue . '%')));

        $templates = (clone $query)->orderBy('created_at', 'desc')->skip($start)->take($length)->get();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => (clone $base)->count(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $templates->map(fn (Template $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'template' => $t->template,
                'type' => $t->type,
                'created_at' => $t->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $t->updated_at->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        if (!$this->isOwner($project)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template' => 'required|string',
            'type' => 'required|in:initial,final,extraction',
        ]);
        $template = $project->templates()->create($validated + ['user_id' => Auth::id()]);

        return response()->json(['success' => true, 'message' => 'Template created successfully.', 'data' => $template]);
    }

    public function update(Request $request, Project $project, Template $template)
    {
        if (!$this->isOwner($project) || $template->project_id !== $project->id) {
            return $this->forbidden('Unauthorized access to this template.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template' => 'required|string',
            'type' => 'required|in:initial,final,extraction',
        ]);
        $template->update($validated);

        return response()->json(['success' => true, 'message' => 'Template updated successfully.', 'data' => $template]);
    }

    public function destroy(Project $project, Template $template)
    {
        if (!$this->isOwner($project) || $template->project_id !== $project->id) {
            return $this->forbidden('Unauthorized access to this template.');
        }

        $template->delete();

        return response()->json(['success' => true, 'message' => 'Template deleted successfully.']);
    }

    /**
     * The owner's templates outside this project (other projects, or not linked to any project),
     * of one type, to copy into it.
     */
    public function copyable(Request $request, Project $project)
    {
        if (!$this->isOwner($project)) {
            return $this->forbidden();
        }

        $type = in_array($request->get('type'), ['initial', 'final', 'extraction'], true) ? $request->get('type') : 'initial';
        $ownProjects = Project::where('user_id', Auth::id())->pluck('name', 'id');

        $templates = Template::with('project:id,name')
            ->where('type', $type)
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('project_id')->where('user_id', Auth::id()))
                ->orWhereIn('project_id', $ownProjects->keys()->reject(fn ($id) => $id === $project->id)))
            ->orderBy('project_id')->orderBy('name')
            ->get(['id', 'name', 'type', 'project_id']);

        return response()->json([
            'success' => true,
            'data' => $templates->map(fn (Template $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'project' => $t->project?->name,
            ]),
        ]);
    }

    /**
     * Copy one of the owner's templates (see copyable) into this project.
     */
    public function copy(Request $request, Project $project)
    {
        if (!$this->isOwner($project)) {
            return $this->forbidden();
        }

        $validated = $request->validate(['template_id' => 'required|integer']);
        $source = Template::with('project')->find($validated['template_id']);
        $allowed = $source && $source->project_id !== $project->id && (
            ($source->project_id === null && $source->user_id === Auth::id())
            || ($source->project && $source->project->user_id === Auth::id())
        );
        if (!$allowed) {
            return response()->json(['success' => false, 'message' => 'Template not found.'], 404);
        }

        $copy = $project->templates()->create([
            'user_id' => Auth::id(),
            'name' => $source->name,
            'template' => $source->template,
            'type' => $source->type,
        ]);

        return response()->json(['success' => true, 'message' => 'Template copied into the project.', 'data' => $copy]);
    }
}

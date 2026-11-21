<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SearchString;
use App\Models\Study;
use App\Services\DatabaseSearch;
use App\Services\DatabaseSearchException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Runs a project's search strings on database APIs (IEEE Xplore, Scopus, OpenAlex, PubMed)
 * from the Studies page and imports the results the user selects.
 */
class DatabaseSearchController extends Controller
{
    /** User columns holding each API's keys (all stored encrypted). */
    private const KEY_FIELDS = [
        'ieee' => ['ieee_api_key'],
        'scopus' => ['scopus_api_key', 'scopus_insttoken'],
        'openalex' => [],
        'pubmed' => ['pubmed_api_key'],
    ];

    private function authorizeOwner(Project $project): void
    {
        if ($project->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Search strings linked to an API and which keys the user has saved (never the keys).
     */
    public function config(Project $project)
    {
        $this->authorizeOwner($project);
        $user = Auth::user();

        $keys = [];
        foreach (self::KEY_FIELDS as $fields) {
            foreach ($fields as $field) {
                $keys[$field] = !empty($user->{$field});
            }
        }

        return response()->json([
            'sources' => SearchString::API_SOURCES,
            'page_sizes' => DatabaseSearch::PAGE_SIZES,
            'max_results' => DatabaseSearch::MAX_RESULTS,
            'search_strings' => $project->searchStrings()->orderBy('database')->get(['id', 'database', 'api_source', 'string']),
            'saved_keys' => $keys,
        ]);
    }

    /**
     * One page of results of a search string, each flagged when the project already has it.
     */
    public function search(Request $request, Project $project, DatabaseSearch $search)
    {
        $this->authorizeOwner($project);

        $validated = $request->validate([
            'source' => ['required', Rule::in(array_keys(SearchString::API_SOURCES))],
            'search_string_id' => ['required', Rule::exists('search_strings', 'id')->where('project_id', $project->id)],
            'query' => 'required|string|max:10000',
            'page' => 'nullable|integer|min:1',
            'keys' => 'nullable|array',
            'keys.*' => 'nullable|string|max:255',
            'clear_keys' => 'nullable|array',
            'clear_keys.*' => 'string',
        ]);

        $user = Auth::user();
        $this->saveKeys($user, $validated['source'], $validated['keys'] ?? [], $validated['clear_keys'] ?? []);

        try {
            $result = $search->search(
                $validated['source'],
                $validated['query'],
                (int) ($validated['page'] ?? 1),
                $user->only(['ieee_api_key', 'scopus_api_key', 'scopus_insttoken', 'pubmed_api_key'])
            );
        } catch (DatabaseSearchException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        [$dois, $titles] = $this->existingKeys($project);
        foreach ($result['items'] as &$item) {
            $item['in_project'] = ($item['doi'] && isset($dois[strtolower($item['doi'])]))
                || ($item['name'] && isset($titles[$this->titleKey($item['name'])]));
        }
        unset($item);

        return response()->json(['success' => true] + $result);
    }

    /**
     * Create studies from the selected results, linked to the search string.
     */
    public function import(Request $request, Project $project)
    {
        $this->authorizeOwner($project);

        $validated = $request->validate([
            'source' => ['required', Rule::in(array_keys(SearchString::API_SOURCES))],
            'search_string_id' => ['required', Rule::exists('search_strings', 'id')->where('project_id', $project->id)],
            'items' => 'required|array|min:1|max:500',
            'items.*.name' => 'required|string|max:255',
            'items.*.authors' => 'nullable|string|max:65000',
            'items.*.year' => 'nullable|integer|min:1900|max:2100',
            'items.*.doi' => 'nullable|string|max:255',
            'items.*.keywords' => 'nullable|string|max:65000',
            'items.*.abstract' => 'nullable|string|max:65000',
            'items.*.link' => 'nullable|string|max:255',
            'items.*.journal_conference' => 'nullable|string|max:255',
            'items.*.document_type' => 'nullable|string|max:255',
        ]);

        $searchString = SearchString::find($validated['search_string_id']);
        $method = SearchString::API_SOURCES[$validated['source']] . ' API';
        $fields = ['name', 'authors', 'year', 'doi', 'keywords', 'abstract', 'link', 'journal_conference', 'document_type'];

        // Custom IDs continue from the project's highest S<n>; the lock keeps two imports apart
        $imported = Cache::lock('study_custom_id_' . $project->id, 30)->block(30, function () use ($validated, $project, $searchString, $method, $fields) {
            $next = $this->nextCustomIdNumber($project);
            $count = 0;
            DB::transaction(function () use ($validated, $project, $searchString, $method, $fields, &$next, &$count) {
                foreach ($validated['items'] as $item) {
                    $data = array_filter(array_intersect_key($item, array_flip($fields)), fn ($v) => $v !== null && $v !== '');
                    Study::create($data + [
                        'project_id' => $project->id,
                        'search_string_id' => $searchString->id,
                        'source' => $searchString->database,
                        'registration_method' => $method,
                        'custom_id' => 'S' . $next++,
                    ]);
                    $count++;
                }
            });

            return $count;
        });

        Cache::forget('menu_data_user_' . Auth::id());
        Log::info('DatabaseSearch: studies imported', [
            'project_id' => $project->id,
            'search_string_id' => $searchString->id,
            'source' => $validated['source'],
            'imported' => $imported,
        ]);

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'message' => "{$imported} studies added from {$searchString->database}.",
        ]);
    }

    /**
     * Save the keys typed for this source (an empty field keeps the saved key unless cleared).
     */
    private function saveKeys($user, string $source, array $keys, array $clear): void
    {
        $changed = false;
        foreach (self::KEY_FIELDS[$source] as $field) {
            if (in_array($field, $clear, true)) {
                $user->{$field} = null;
                $changed = true;
            } elseif (!empty($keys[$field])) {
                $user->{$field} = trim($keys[$field]);
                $changed = true;
            }
        }
        if ($changed) {
            $user->save();
        }
    }

    /** Lowercase DOIs and normalized titles of the project's studies. */
    private function existingKeys(Project $project): array
    {
        $dois = [];
        $titles = [];
        foreach ($project->studies()->get(['doi', 'name']) as $study) {
            if ($study->doi) {
                $dois[strtolower(trim($study->doi))] = true;
            }
            if ($study->name) {
                $titles[$this->titleKey($study->name)] = true;
            }
        }

        return [$dois, $titles];
    }

    private function titleKey(string $title): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(\Illuminate\Support\Str::ascii($title)));
    }

    private function nextCustomIdNumber(Project $project): int
    {
        $max = 0;
        foreach ($project->studies()->where('custom_id', 'like', 'S%')->pluck('custom_id') as $id) {
            if (preg_match('/^S(\d+)$/', $id, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }
}

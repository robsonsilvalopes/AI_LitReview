{{--
    Optional "Database" field for the Import Excel/CSV modals (#excel_search_string_id).
    Needs $project; uses $searchStrings when the page already loaded them.
    The page's submit script sends its value as search_string_id.
--}}
@php
    $excelSearchStrings = $searchStrings ?? $project->searchStrings()->orderBy('database')->get();
@endphp
<div class="mb-3">
    <label for="excel_search_string_id" class="form-label">Database</label>
    <select id="excel_search_string_id" name="search_string_id" class="form-select">
        <option value="">No database (use the "source" column, if any)</option>
        @foreach($excelSearchStrings as $excelSearchString)
        <option value="{{ $excelSearchString->id }}" title="{{ $excelSearchString->string }}">
            {{ $excelSearchString->database }} — {{ \Illuminate\Support\Str::limit($excelSearchString->string, 60) }}
        </option>
        @endforeach
    </select>
    <small class="text-muted">
        @if($excelSearchStrings->isEmpty())
        No databases registered yet. Add a search string with its database on the <a href="{{ route('projects.show', $project) }}">project page</a> to link the imported studies to it.
        @else
        Databases registered with the project's search strings. When selected, every imported study is linked to it.
        @endif
    </small>
</div>

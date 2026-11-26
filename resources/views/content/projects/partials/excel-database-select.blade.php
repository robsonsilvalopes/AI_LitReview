{{--
    Required "Database" field for the Import Excel/CSV modals (#excel_search_string_id).
    Needs $project; uses $searchStrings when the page already loaded them.
    The page's submit script sends its value as search_string_id.
--}}
@php
    $excelSearchStrings = $searchStrings ?? $project->searchStrings()->orderBy('database')->get();
@endphp
<div class="mb-3">
    <label for="excel_search_string_id" class="form-label">Database <span class="text-danger">*</span></label>
    <select id="excel_search_string_id" name="search_string_id" class="form-select" required>
        <option value="">Select the database the file came from</option>
        @foreach($excelSearchStrings as $excelSearchString)
        <option value="{{ $excelSearchString->id }}" title="{{ $excelSearchString->string }}">
            {{ $excelSearchString->database }} — {{ \Illuminate\Support\Str::limit($excelSearchString->string, 60) }}
        </option>
        @endforeach
    </select>
    @if($excelSearchStrings->isEmpty())
    <small class="text-danger">
        No databases registered yet. Add a search string with its database on the <a href="{{ route('projects.show', $project) }}">project page</a> before importing an Excel/CSV file.
    </small>
    @else
    <small class="text-muted">Databases registered with the project's search strings. Every imported study is linked to the selected one.</small>
    @endif
</div>

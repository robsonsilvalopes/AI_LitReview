<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\dashboard\Analytics;
use App\Http\Controllers\authentications\LoginBasic;
use App\Http\Controllers\authentications\RegisterBasic;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AiModelController;
use App\Http\Controllers\AvailableModelController;
use App\Http\Controllers\TemplateController;

// Authentication routes (public - no auth required)
Route::get('/login', [LoginBasic::class, 'index'])->name('login');
Route::post('/login', [LoginBasic::class, 'login'])->name('login.post');
Route::get('/register', [RegisterBasic::class, 'index'])->name('register');
Route::post('/register', [RegisterBasic::class, 'register'])->name('register.post');

// S3 File serving (public - no auth required for images)
// Must be defined before other routes to avoid conflicts
Route::get('/storage/s3/{path}', function ($path) {
    try {
        // Log the request
        \Log::info('Serving S3 file request', [
            'path' => $path,
            'request_path' => request()->path(),
            'request_uri' => request()->getRequestUri(),
            'full_url' => request()->fullUrl()
        ]);

        // Decode URL-encoded path
        $path = urldecode($path);

        // Check if file exists
        if (!Storage::disk()->exists($path)) {
            \Log::warning('S3 file not found', ['path' => $path]);
            abort(404, 'File not found: ' . $path);
        }

        // Get file content
        $file = Storage::disk()->get($path);

        // Try to get mime type
        $mimeType = 'application/octet-stream';
        try {
            $mimeType = Storage::disk()->mimeType($path);
        } catch (\Exception $e) {
            // Try to guess from extension
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
            ];
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
        }

        \Log::info('S3 file served successfully', [
            'path' => $path,
            'mime' => $mimeType,
            'size' => strlen($file)
        ]);

        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'public, max-age=31536000')
            ->header('Content-Length', strlen($file));
    } catch (\Exception $e) {
        \Log::error('Error serving S3 file', [
            'path' => $path ?? 'unknown',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        abort(404, 'File not found');
    }
})->where('path', '.*')->name('storage.s3');

// Route::group(['middleware' => 'web'], function () {
//  \Laravel\Pulse\Facades\Pulse::routes();
// });

// All other routes require authentication
Route::middleware('auth')->group(function () {
    // Home
    Route::get('/', [Analytics::class, 'index'])->name('home');

    // Logout
    Route::post('/logout', [LoginBasic::class, 'logout'])->name('logout');

    // Profile
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');

    // Settings
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'show'])->name('settings');
    Route::post('/settings', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');

    // AI Models
    Route::get('/ai-models', [AiModelController::class, 'index'])->name('ai-models');
    Route::get('/ai-models/data', [AiModelController::class, 'data'])->name('ai-models.data');
    Route::get('/ai-models/local', [AiModelController::class, 'localModels'])->name('ai-models.local');
    Route::get('/ai-models/available', [AvailableModelController::class, 'index'])->name('ai-models.available.index');
    Route::post('/ai-models/available', [AvailableModelController::class, 'store'])->name('ai-models.available.store');
    Route::get('/ai-models/available/{availableModel}', [AvailableModelController::class, 'show'])->name('ai-models.available.show');
    Route::delete('/ai-models/available/{availableModel}', [AvailableModelController::class, 'destroy'])->name('ai-models.available.destroy');

    // Templates
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::match(['put', 'patch'], '/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

    // Projects
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::match(['put', 'patch'], '/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Project Collaborators
    Route::get('/projects/{project}/collaborators', [ProjectController::class, 'getCollaborators'])->name('projects.collaborators.index');
    Route::post('/projects/{project}/collaborators', [ProjectController::class, 'addCollaborator'])->name('projects.collaborators.store');
    Route::delete('/projects/{project}/collaborators/{user}', [ProjectController::class, 'removeCollaborator'])->name('projects.collaborators.destroy');

    // Research Questions (nested under projects)
    Route::get('/projects/{project}/research-questions', [\App\Http\Controllers\ResearchQuestionController::class, 'index'])->name('research-questions.index');
    Route::post('/projects/{project}/research-questions', [\App\Http\Controllers\ResearchQuestionController::class, 'store'])->name('research-questions.store');
    Route::match(['put', 'patch'], '/projects/{project}/research-questions/{researchQuestion}', [\App\Http\Controllers\ResearchQuestionController::class, 'update'])->name('research-questions.update');
    Route::delete('/projects/{project}/research-questions/{researchQuestion}', [\App\Http\Controllers\ResearchQuestionController::class, 'destroy'])->name('research-questions.destroy');

    // Search Strings (nested under projects)
    Route::get('/projects/{project}/search-strings', [\App\Http\Controllers\SearchStringController::class, 'index'])->name('search-strings.index');
    Route::post('/projects/{project}/search-strings', [\App\Http\Controllers\SearchStringController::class, 'store'])->name('search-strings.store');
    Route::match(['put', 'patch'], '/projects/{project}/search-strings/{searchString}', [\App\Http\Controllers\SearchStringController::class, 'update'])->name('search-strings.update');
    Route::delete('/projects/{project}/search-strings/{searchString}', [\App\Http\Controllers\SearchStringController::class, 'destroy'])->name('search-strings.destroy');

    // Inclusion Criterias (nested under projects)
    Route::get('/projects/{project}/inclusion-criterias', [\App\Http\Controllers\InclusionCriteriaController::class, 'index'])->name('inclusion-criterias.index');
    Route::post('/projects/{project}/inclusion-criterias', [\App\Http\Controllers\InclusionCriteriaController::class, 'store'])->name('inclusion-criterias.store');
    Route::match(['put', 'patch'], '/projects/{project}/inclusion-criterias/{inclusionCriteria}', [\App\Http\Controllers\InclusionCriteriaController::class, 'update'])->name('inclusion-criterias.update');
    Route::delete('/projects/{project}/inclusion-criterias/{inclusionCriteria}', [\App\Http\Controllers\InclusionCriteriaController::class, 'destroy'])->name('inclusion-criterias.destroy');

    // Exclusion Criterias (nested under projects)
    Route::get('/projects/{project}/exclusion-criterias', [\App\Http\Controllers\ExclusionCriteriaController::class, 'index'])->name('exclusion-criterias.index');
    Route::post('/projects/{project}/exclusion-criterias', [\App\Http\Controllers\ExclusionCriteriaController::class, 'store'])->name('exclusion-criterias.store');
    Route::match(['put', 'patch'], '/projects/{project}/exclusion-criterias/{exclusionCriteria}', [\App\Http\Controllers\ExclusionCriteriaController::class, 'update'])->name('exclusion-criterias.update');
    Route::delete('/projects/{project}/exclusion-criterias/{exclusionCriteria}', [\App\Http\Controllers\ExclusionCriteriaController::class, 'destroy'])->name('exclusion-criterias.destroy');

    // Extraction Data (nested under projects)
    Route::get('/projects/{project}/extraction-data', [\App\Http\Controllers\ExtractionDataController::class, 'index'])->name('extraction-data.index');
    Route::post('/projects/{project}/extraction-data', [\App\Http\Controllers\ExtractionDataController::class, 'store'])->name('extraction-data.store');
    Route::match(['put', 'patch'], '/projects/{project}/extraction-data/{extractionData}', [\App\Http\Controllers\ExtractionDataController::class, 'update'])->name('extraction-data.update');
    Route::delete('/projects/{project}/extraction-data/{extractionData}', [\App\Http\Controllers\ExtractionDataController::class, 'destroy'])->name('extraction-data.destroy');

    // Studies (nested under projects)
    Route::get('/projects/{project}/studies', [\App\Http\Controllers\StudyController::class, 'index'])->name('projects.studies');
    Route::get('/projects/{project}/studies/{study}/screenings', [\App\Http\Controllers\StudyController::class, 'getStudyScreenings'])->name('studies.screenings');
    Route::get('/projects/{project}/studies/{study}/markdown', [\App\Http\Controllers\StudyController::class, 'markdown'])->name('studies.markdown');
    Route::get('/projects/{project}/duplicates', [\App\Http\Controllers\StudyController::class, 'duplicates'])->name('projects.duplicates');
    Route::get('/projects/{project}/initial', [\App\Http\Controllers\StudyController::class, 'initial'])->name('projects.initial');
    Route::get('/projects/{project}/final', [\App\Http\Controllers\StudyController::class, 'final'])->name('projects.final');
    Route::get('/projects/{project}/extraction', [\App\Http\Controllers\StudyController::class, 'extraction'])->name('projects.extraction');
    Route::get('/projects/{project}/report', [\App\Http\Controllers\StudyController::class, 'report'])->name('projects.report');
    Route::get('/projects/{project}/report/generate', [\App\Http\Controllers\StudyController::class, 'generateReport'])->name('projects.report.generate');
    Route::get('/projects/{project}/report/print', [\App\Http\Controllers\StudyController::class, 'printReport'])->name('projects.report.print');
    Route::post('/projects/{project}/studies', [\App\Http\Controllers\StudyController::class, 'store'])->name('studies.store');
    Route::post('/projects/{project}/studies/import-excel', [\App\Http\Controllers\StudyController::class, 'importExcel'])->name('studies.import-excel');
    Route::post('/projects/{project}/studies/import-bibliography', [\App\Http\Controllers\StudyController::class, 'importBibliography'])->name('studies.import-bibliography');
    Route::post('/projects/{project}/studies/bulk-upload', [\App\Http\Controllers\StudyController::class, 'bulkUpload'])->name('studies.bulk-upload');
    Route::post('/projects/{project}/studies/fetch-pdfs', [\App\Http\Controllers\StudyController::class, 'fetchOpenAccessPdfs'])->name('studies.fetch-pdfs');
    Route::get('/projects/{project}/studies/fetch-pdfs/status', [\App\Http\Controllers\StudyController::class, 'fetchOpenAccessPdfsStatus'])->name('studies.fetch-pdfs-status');
    Route::post('/projects/{project}/studies/assign-pdf', [\App\Http\Controllers\StudyController::class, 'assignPdf'])->name('studies.assign-pdf');
    Route::post('/projects/{project}/studies/delete-duplicates', [\App\Http\Controllers\StudyController::class, 'deleteDuplicates'])->name('studies.delete-duplicates');
    Route::post('/projects/{project}/studies/include-na', [\App\Http\Controllers\StudyController::class, 'includeNa'])->name('studies.include-na');
    Route::get('/projects/{project}/studies/download-included', [\App\Http\Controllers\StudyController::class, 'downloadIncluded'])->name('studies.download-included');
    Route::post('/projects/{project}/studies/{study}/duplicates-status', [\App\Http\Controllers\StudyController::class, 'updateDuplicatesStatus'])->name('studies.duplicates-status');
    // Initial Selection routes
    Route::post('/projects/{project}/studies/include-initial-na', [\App\Http\Controllers\StudyController::class, 'includeInitialNa'])->name('studies.include-initial-na');
    Route::get('/projects/{project}/studies/download-initial-included', [\App\Http\Controllers\StudyController::class, 'downloadInitialIncluded'])->name('studies.download-initial-included');
    Route::get('/projects/{project}/studies/download-final-included', [\App\Http\Controllers\StudyController::class, 'downloadFinalIncluded'])->name('studies.download-final-included');
    Route::post('/projects/{project}/studies/{study}/initial-status', [\App\Http\Controllers\StudyController::class, 'updateInitialStatus'])->name('studies.initial-status');
    Route::post('/projects/{project}/studies/{study}/final-status', [\App\Http\Controllers\StudyController::class, 'updateFinalStatus'])->name('studies.final-status');
    Route::post('/projects/{project}/studies/{study}/screening-status', [\App\Http\Controllers\StudyController::class, 'updateScreeningStatus'])->name('studies.screening-status');
    Route::match(['put', 'patch'], '/projects/{project}/studies/{study}', [\App\Http\Controllers\StudyController::class, 'update'])->name('studies.update');
    Route::delete('/projects/{project}/studies/{study}', [\App\Http\Controllers\StudyController::class, 'destroy'])->name('studies.destroy');

    // Initial Screenings (nested under projects)
    // IMPORTANT: More specific routes must come BEFORE routes with parameters
    Route::get('/projects/{project}/initial-screenings/available-models', [\App\Http\Controllers\InitialScreeningController::class, 'getAvailableModels'])->name('initial-screenings.available-models');
    Route::get('/projects/{project}/initial-screenings/templates', [\App\Http\Controllers\InitialScreeningController::class, 'getTemplates'])->name('initial-screenings.templates');
    Route::get('/projects/{project}/initial-screenings/users', [\App\Http\Controllers\InitialScreeningController::class, 'getUsers'])->name('initial-screenings.users');
    Route::post('/projects/{project}/initial-screenings/{initialScreening}/process', [\App\Http\Controllers\InitialScreeningController::class, 'process'])->name('initial-screenings.process');
    Route::get('/projects/{project}/initial-screenings/{initialScreening}/download-studies-xls', [\App\Http\Controllers\InitialScreeningController::class, 'downloadStudiesXls'])
        ->scopeBindings()
        ->name('initial-screenings.download-studies-xls');
    Route::get('/projects/{project}/initial-screenings', [\App\Http\Controllers\InitialScreeningController::class, 'index'])->name('initial-screenings.index');
    // Use scoped route model binding to ensure InitialScreening belongs to Project
    Route::get('/projects/{project}/initial-screenings/{initialScreening}', [\App\Http\Controllers\InitialScreeningController::class, 'show'])
        ->scopeBindings()
        ->name('initial-screenings.show');
    Route::post('/projects/{project}/initial-screenings', [\App\Http\Controllers\InitialScreeningController::class, 'store'])->name('initial-screenings.store');
    Route::match(['put', 'patch'], '/projects/{project}/initial-screenings/{initialScreening}', [\App\Http\Controllers\InitialScreeningController::class, 'update'])
        ->scopeBindings()
        ->name('initial-screenings.update');
    Route::delete('/projects/{project}/initial-screenings/{initialScreening}', [\App\Http\Controllers\InitialScreeningController::class, 'destroy'])
        ->scopeBindings()
        ->name('initial-screenings.destroy');

    // Final Screenings (nested under projects)
    // IMPORTANT: More specific routes must come BEFORE routes with parameters
    Route::get('/projects/{project}/final-screenings/available-models', [\App\Http\Controllers\FinalScreeningController::class, 'getAvailableModels'])->name('final-screenings.available-models');
    Route::get('/projects/{project}/final-screenings/templates', [\App\Http\Controllers\FinalScreeningController::class, 'getTemplates'])->name('final-screenings.templates');
    Route::get('/projects/{project}/final-screenings/users', [\App\Http\Controllers\FinalScreeningController::class, 'getUsers'])->name('final-screenings.users');
    Route::post('/projects/{project}/final-screenings/{finalScreening}/process', [\App\Http\Controllers\FinalScreeningController::class, 'process'])->name('final-screenings.process');
    Route::get('/projects/{project}/final-screenings/{finalScreening}/download-studies-xls', [\App\Http\Controllers\FinalScreeningController::class, 'downloadStudiesXls'])
        ->scopeBindings()
        ->name('final-screenings.download-studies-xls');
    Route::get('/projects/{project}/final-screenings', [\App\Http\Controllers\FinalScreeningController::class, 'index'])->name('final-screenings.index');
    // Use scoped route model binding to ensure FinalScreening belongs to Project
    Route::get('/projects/{project}/final-screenings/{finalScreening}', [\App\Http\Controllers\FinalScreeningController::class, 'show'])
        ->scopeBindings()
        ->name('final-screenings.show');
    Route::post('/projects/{project}/final-screenings', [\App\Http\Controllers\FinalScreeningController::class, 'store'])->name('final-screenings.store');
    Route::match(['put', 'patch'], '/projects/{project}/final-screenings/{finalScreening}', [\App\Http\Controllers\FinalScreeningController::class, 'update'])
        ->scopeBindings()
        ->name('final-screenings.update');
    Route::delete('/projects/{project}/final-screenings/{finalScreening}', [\App\Http\Controllers\FinalScreeningController::class, 'destroy'])
        ->scopeBindings()
        ->name('final-screenings.destroy');

    // Extractions
    Route::get('/projects/{project}/extractions/available-models', [\App\Http\Controllers\ExtractionController::class, 'getAvailableModels'])->name('extractions.available-models');
    Route::get('/projects/{project}/extractions/templates', [\App\Http\Controllers\ExtractionController::class, 'getTemplates'])->name('extractions.templates');
    Route::get('/projects/{project}/extractions/users', [\App\Http\Controllers\ExtractionController::class, 'getUsers'])->name('extractions.users');
    Route::post('/projects/{project}/extractions/{extraction}/process', [\App\Http\Controllers\ExtractionController::class, 'process'])->name('extractions.process');
    Route::get('/projects/{project}/extractions', [\App\Http\Controllers\ExtractionController::class, 'index'])->name('extractions.index');
    Route::get('/projects/{project}/extractions/{extraction}', [\App\Http\Controllers\ExtractionController::class, 'show'])
        ->scopeBindings()
        ->name('extractions.show');
    Route::post('/projects/{project}/extractions', [\App\Http\Controllers\ExtractionController::class, 'store'])->name('extractions.store');
    Route::post('/projects/{project}/extractions/{extraction}/studies/{study}/extraction-data', [\App\Http\Controllers\ExtractionController::class, 'saveStudyExtractionData'])
        ->name('extractions.save-study-data');
    Route::get('/projects/{project}/extractions/{extraction}/download', [\App\Http\Controllers\ExtractionController::class, 'downloadExtractionData'])
        ->scopeBindings()
        ->name('extractions.download');
    Route::delete('/projects/{project}/extractions/{extraction}', [\App\Http\Controllers\ExtractionController::class, 'destroy'])
        ->scopeBindings()
        ->name('extractions.destroy');
});

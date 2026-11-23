<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose rows point to a template, with the project they belong to. */
    private const USERS_OF_TEMPLATES = ['initial_screenings', 'final_screenings', 'extractions'];

    /**
     * Templates belong to a project. Existing templates are linked to the project whose
     * screenings or extractions use them; a template used by several projects is copied so each
     * project has its own; an unused template goes to its owner's project when the owner has a
     * single one, otherwise it stays unlinked (it can be copied into a project from the
     * project's Templates page).
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        DB::transaction(fn () => $this->linkExistingTemplates());
    }

    /** Link the templates without a project (see up()). */
    public function linkExistingTemplates(): void
    {
        foreach (DB::table('templates')->whereNull('project_id')->orderBy('id')->get() as $template) {
            $projects = collect(self::USERS_OF_TEMPLATES)
                ->flatMap(fn ($table) => DB::table($table)->where('template_id', $template->id)->pluck('project_id'))
                ->filter()->unique()->sort()->values();

            if ($projects->isEmpty()) {
                $owned = DB::table('projects')->where('user_id', $template->user_id)->pluck('id');
                if ($owned->count() === 1) {
                    DB::table('templates')->where('id', $template->id)->update(['project_id' => $owned->first()]);
                }
                continue;
            }

            // The first project keeps the template; every other project gets a copy
            DB::table('templates')->where('id', $template->id)->update(['project_id' => $projects->first()]);
            foreach ($projects->slice(1) as $projectId) {
                $copy = (array) $template;
                unset($copy['id']);
                $copy['project_id'] = $projectId;
                $copyId = DB::table('templates')->insertGetId($copy);
                foreach (self::USERS_OF_TEMPLATES as $table) {
                    DB::table($table)->where('project_id', $projectId)->where('template_id', $template->id)
                        ->update(['template_id' => $copyId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};

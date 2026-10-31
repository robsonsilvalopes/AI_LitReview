<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `templates` was created without `project_id` in the current chain; some DBs never had
     * this column or use a different FK name. Only drop when present.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('templates', 'project_id')) {
            return;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $constraintNames = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'templates')
            ->where('COLUMN_NAME', 'project_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->distinct()
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraintNames as $name) {
            if (! is_string($name) || ! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                continue;
            }
            DB::statement('ALTER TABLE `templates` DROP FOREIGN KEY `'.$name.'`');
        }

        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'project_id')) {
                $table->dropColumn('project_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('templates', 'project_id')) {
            return;
        }

        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });
    }
};

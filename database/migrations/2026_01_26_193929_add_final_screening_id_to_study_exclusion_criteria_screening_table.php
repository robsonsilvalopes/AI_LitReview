<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('study_exclusion_criteria_screening', function (Blueprint $table) {
            // Make initial_screening_id nullable first
            $table->foreignId('initial_screening_id')->nullable()->change();
        });
        
        // Add final_screening_id column first (without foreign key) - check if it doesn't exist
        if (!Schema::hasColumn('study_exclusion_criteria_screening', 'final_screening_id')) {
            Schema::table('study_exclusion_criteria_screening', function (Blueprint $table) {
                $table->unsignedBigInteger('final_screening_id')->nullable()->after('initial_screening_id');
            });
        }
        
        // Get all foreign keys that reference columns in the unique index
        $foreignKeys = DB::select("SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'study_exclusion_criteria_screening' AND REFERENCED_TABLE_NAME IS NOT NULL");
        
        // Drop all foreign keys first
        foreach ($foreignKeys as $fk) {
            try {
                DB::statement("ALTER TABLE study_exclusion_criteria_screening DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
        }
        
        // Drop old unique constraint (must be done after removing foreign keys)
        Schema::table('study_exclusion_criteria_screening', function (Blueprint $table) {
            try {
                $table->dropUnique('study_ec_screening_unique');
            } catch (\Exception $e) {
                // Constraint might not exist
            }
        });
        
        // Recreate foreign keys
        Schema::table('study_exclusion_criteria_screening', function (Blueprint $table) {
            $table->foreign('study_id')->references('id')->on('studies')->onDelete('cascade');
            $table->foreign('exclusion_criteria_id')->references('id')->on('exclusion_criterias')->onDelete('cascade');
            $table->foreign('initial_screening_id')->references('id')->on('initial_screenings')->onDelete('cascade');
        });
        
        // Add new unique constraint that includes both screening types
        Schema::table('study_exclusion_criteria_screening', function (Blueprint $table) {
            $table->unique(['study_id', 'exclusion_criteria_id', 'initial_screening_id', 'final_screening_id'], 'study_ec_screening_unique');
        });
        
        // Add foreign key constraint if it doesn't exist
        Schema::table('study_exclusion_criteria_screening', function (Blueprint $table) {
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'study_exclusion_criteria_screening' AND COLUMN_NAME = 'final_screening_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
            if (empty($foreignKeys)) {
                $table->foreign('final_screening_id')->references('id')->on('final_screenings')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('study_exclusion_criteria_screening', function (Blueprint $table) {
            // Drop new unique constraint
            $table->dropUnique('study_ec_screening_unique');
            
            // Restore old unique constraint
            $table->unique(['study_id', 'exclusion_criteria_id', 'initial_screening_id'], 'study_ec_screening_unique');
            
            // Remove final_screening_id
            $table->dropForeign(['final_screening_id']);
            $table->dropColumn('final_screening_id');
            
            // Make initial_screening_id not nullable again
            $table->foreignId('initial_screening_id')->nullable(false)->change();
        });
    }
};

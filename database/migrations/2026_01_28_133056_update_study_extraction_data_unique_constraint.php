<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('study_extraction_data', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['study_id']);
            $table->dropForeign(['extraction_data_id']);
            $table->dropForeign(['extraction_id']);
            
            // Drop the old unique constraint
            $table->dropUnique('study_extraction_data_unique');
            
            // Create new unique constraint that includes extraction_id
            $table->unique(['study_id', 'extraction_data_id', 'extraction_id'], 'study_extraction_data_unique');
            
            // Re-add foreign keys
            $table->foreign('study_id')->references('id')->on('studies')->onDelete('cascade');
            $table->foreign('extraction_data_id')->references('id')->on('extraction_data')->onDelete('cascade');
            $table->foreign('extraction_id')->references('id')->on('extractions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('study_extraction_data', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign(['study_id']);
            $table->dropForeign(['extraction_data_id']);
            $table->dropForeign(['extraction_id']);
            
            // Drop the new unique constraint
            $table->dropUnique('study_extraction_data_unique');
            
            // Restore old unique constraint (without extraction_id)
            $table->unique(['study_id', 'extraction_data_id'], 'study_extraction_data_unique');
            
            // Re-add foreign keys
            $table->foreign('study_id')->references('id')->on('studies')->onDelete('cascade');
            $table->foreign('extraction_data_id')->references('id')->on('extraction_data')->onDelete('cascade');
            $table->foreign('extraction_id')->references('id')->on('extractions')->onDelete('cascade');
        });
    }
};

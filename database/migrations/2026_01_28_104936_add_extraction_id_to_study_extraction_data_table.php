<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MySQL rejects dropping the unique index while foreign keys still depend on those columns (error 1553).
     * Drop FKs first, then the unique, then add the column and recreate FKs and the composite unique.
     */
    public function up(): void
    {
        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->dropForeign(['study_id']);
            $table->dropForeign(['extraction_data_id']);
        });

        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->dropUnique('study_extraction_data_unique');
        });

        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->foreignId('extraction_id')->after('extraction_data_id')->constrained('extractions')->onDelete('cascade');
        });

        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->unique(['study_id', 'extraction_data_id', 'extraction_id'], 'study_extraction_data_unique');
        });

        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->foreign('study_id')->references('id')->on('studies')->onDelete('cascade');
            $table->foreign('extraction_data_id')->references('id')->on('extraction_data')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->dropForeign(['study_id']);
            $table->dropForeign(['extraction_data_id']);
            $table->dropForeign(['extraction_id']);
        });

        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->dropUnique('study_extraction_data_unique');
        });

        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->dropColumn('extraction_id');
        });

        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->unique(['study_id', 'extraction_data_id'], 'study_extraction_data_unique');
        });

        Schema::table('study_extraction_data', function (Blueprint $table) {
            $table->foreign('study_id')->references('id')->on('studies')->onDelete('cascade');
            $table->foreign('extraction_data_id')->references('id')->on('extraction_data')->onDelete('cascade');
        });
    }
};

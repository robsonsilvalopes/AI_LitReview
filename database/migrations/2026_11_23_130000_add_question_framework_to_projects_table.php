<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Research question framework (PICO, PICOC, PCC, SPIDER...) and its elements: description,
     * keywords/synonyms, not applicable, used in the search string draft.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('question_framework', 20)->nullable();
            $table->json('question_framework_elements')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['question_framework', 'question_framework_elements']);
        });
    }
};

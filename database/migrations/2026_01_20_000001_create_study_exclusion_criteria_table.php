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
        Schema::create('study_exclusion_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_id')->constrained()->onDelete('cascade');
            $table->foreignId('exclusion_criteria_id')->constrained('exclusion_criterias')->onDelete('cascade');
            $table->enum('type', ['duplicates', 'initial', 'final']);
            $table->timestamps();

            // Prevent duplicate entries for the same study, criteria, and type
            $table->unique(['study_id', 'exclusion_criteria_id', 'type'], 'study_ec_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_exclusion_criteria');
    }
};

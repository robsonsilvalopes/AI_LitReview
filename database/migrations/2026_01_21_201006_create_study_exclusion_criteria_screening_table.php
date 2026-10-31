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
        Schema::create('study_exclusion_criteria_screening', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_id')->constrained()->onDelete('cascade');
            $table->foreignId('exclusion_criteria_id')->constrained('exclusion_criterias')->onDelete('cascade');
            $table->foreignId('initial_screening_id')->constrained('initial_screenings')->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate entries for the same study, criteria, and screening
            $table->unique(['study_id', 'exclusion_criteria_id', 'initial_screening_id'], 'study_ec_screening_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_exclusion_criteria_screening');
    }
};

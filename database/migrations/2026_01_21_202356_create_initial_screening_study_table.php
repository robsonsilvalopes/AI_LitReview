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
        Schema::create('initial_screening_study', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initial_screening_id')->constrained('initial_screenings')->onDelete('cascade');
            $table->foreignId('study_id')->constrained()->onDelete('cascade');
            $table->boolean('status')->nullable(); // true = included, false = excluded, null = not evaluated
            $table->timestamps();

            // Prevent duplicate entries for the same screening and study
            $table->unique(['initial_screening_id', 'study_id'], 'screening_study_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('initial_screening_study');
    }
};

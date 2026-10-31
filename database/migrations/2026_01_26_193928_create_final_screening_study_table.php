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
        Schema::create('final_screening_study', function (Blueprint $table) {
            $table->id();
            $table->foreignId('final_screening_id')->constrained('final_screenings')->onDelete('cascade');
            $table->foreignId('study_id')->constrained()->onDelete('cascade');
            $table->boolean('status')->nullable(); // true = included, false = excluded, null = not evaluated
            $table->text('input_ai')->nullable();
            $table->text('output_ai')->nullable();
            $table->bigInteger('total_duration')->nullable()->comment('Duration in milliseconds between request and response');
            $table->integer('input_tokens')->nullable()->comment('Number of tokens sent to the model');
            $table->integer('output_tokens')->nullable()->comment('Number of tokens in the response');
            $table->integer('total_tokens')->nullable()->comment('Total tokens used');
            $table->decimal('cost', 10, 8)->nullable()->default(0)->comment('Total cost (0 for local AI)');
            $table->timestamps();

            // Prevent duplicate entries for the same screening and study
            $table->unique(['final_screening_id', 'study_id'], 'final_screening_study_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('final_screening_study');
    }
};

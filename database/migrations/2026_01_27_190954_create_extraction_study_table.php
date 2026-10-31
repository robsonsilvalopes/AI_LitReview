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
        Schema::create('extraction_study', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extraction_id')->constrained('extractions')->onDelete('cascade');
            $table->foreignId('study_id')->constrained()->onDelete('cascade');
            $table->longText('input_ai')->nullable();
            $table->longText('output_ai')->nullable();
            $table->bigInteger('total_duration')->nullable()->comment('Duration in milliseconds between request and response');
            $table->integer('input_tokens')->nullable()->comment('Number of tokens sent to the model');
            $table->integer('output_tokens')->nullable()->comment('Number of tokens in the response');
            $table->integer('total_tokens')->nullable()->comment('Total tokens used');
            $table->decimal('cost', 10, 8)->nullable()->default(0)->comment('Total cost (0 for local AI)');
            $table->timestamps();
            $table->unique(['extraction_id', 'study_id'], 'extraction_study_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extraction_study');
    }
};

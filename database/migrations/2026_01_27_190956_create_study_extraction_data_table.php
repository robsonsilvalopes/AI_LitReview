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
        Schema::create('study_extraction_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_id')->constrained()->onDelete('cascade');
            $table->foreignId('extraction_data_id')->constrained('extraction_data')->onDelete('cascade');
            $table->text('value')->nullable()->comment('Answer/value for this extraction data field');
            $table->timestamps();
            $table->unique(['study_id', 'extraction_data_id'], 'study_extraction_data_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_extraction_data');
    }
};

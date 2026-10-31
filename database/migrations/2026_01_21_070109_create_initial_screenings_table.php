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
        Schema::create('initial_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('nome');
            $table->enum('tipo', ['Human', 'AI OpenRouter', 'AI Local']);
            $table->foreignId('ai_model_id')->nullable()->constrained('available_models')->onDelete('set null');
            $table->foreignId('responsavel_id')->constrained('users')->onDelete('cascade');
            $table->date('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('initial_screenings');
    }
};

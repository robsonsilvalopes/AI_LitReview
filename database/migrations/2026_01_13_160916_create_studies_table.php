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
        Schema::create('studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('custom_id', 30)->nullable(); // S1, S2, S3... ou customizado
            $table->string('name');
            $table->string('doi')->nullable();
            $table->text('keywords')->nullable();
            $table->text('abstract')->nullable();
            $table->string('link')->nullable();
            $table->boolean('status_selecao_inicial')->nullable();
            $table->boolean('status_selecao_final')->nullable();
            $table->boolean('status_extracao')->nullable();
            $table->string('pdf_path')->nullable(); // Path to PDF in S3
            $table->timestamps();
            
            // Index for faster queries
            $table->index('project_id');
            $table->index('custom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('available_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('model_id');
            $table->string('name');
            $table->json('model_json');
            $table->decimal('price_input', 12, 6)->nullable();
            $table->decimal('price_output', 12, 6)->nullable();
            $table->string('type'); // local | openrouter
            $table->timestamps();

            $table->unique(['user_id', 'model_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('available_models');
    }
};

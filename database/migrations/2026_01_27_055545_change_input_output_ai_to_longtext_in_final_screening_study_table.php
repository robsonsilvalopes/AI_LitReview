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
        Schema::table('final_screening_study', function (Blueprint $table) {
            $table->longText('input_ai')->nullable()->change();
            $table->longText('output_ai')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('final_screening_study', function (Blueprint $table) {
            $table->text('input_ai')->nullable()->change();
            $table->text('output_ai')->nullable()->change();
        });
    }
};

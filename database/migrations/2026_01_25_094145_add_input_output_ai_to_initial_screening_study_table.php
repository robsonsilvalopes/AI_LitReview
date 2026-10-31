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
        Schema::table('initial_screening_study', function (Blueprint $table) {
            $table->text('input_ai')->nullable()->after('status');
            $table->text('output_ai')->nullable()->after('input_ai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('initial_screening_study', function (Blueprint $table) {
            $table->dropColumn(['input_ai', 'output_ai']);
        });
    }
};

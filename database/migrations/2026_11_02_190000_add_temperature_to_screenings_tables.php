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
        foreach (['initial_screenings', 'final_screenings'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->decimal('temperature', 3, 2)->nullable()->after('template_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['initial_screenings', 'final_screenings'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('temperature');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRISMA 2020: manual adjustments of the flow diagram numbers, and the checklist answers
     * (status, location in the manuscript, notes per item).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('prisma_flow')->nullable();
            $table->json('prisma_checklist')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['prisma_flow', 'prisma_checklist']);
        });
    }
};

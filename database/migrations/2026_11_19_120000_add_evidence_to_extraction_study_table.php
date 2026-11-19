<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passages of the study's Markdown the AI quoted for each extraction field:
     * {"<extraction_data_id>": ["quote", ...]}.
     */
    public function up(): void
    {
        Schema::table('extraction_study', function (Blueprint $table) {
            $table->json('evidence')->nullable()->after('output_ai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extraction_study', function (Blueprint $table) {
            $table->dropColumn('evidence');
        });
    }
};

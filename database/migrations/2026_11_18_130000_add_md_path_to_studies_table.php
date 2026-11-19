<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Path of the Markdown generated from the study's PDF (pymupdf4llm).
     */
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->string('md_path')->nullable()->after('pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropColumn('md_path');
        });
    }
};

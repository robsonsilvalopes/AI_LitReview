<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Database API a search string runs on from the Studies page (ieee, scopus, openalex, pubmed).
     */
    public function up(): void
    {
        Schema::table('search_strings', function (Blueprint $table) {
            $table->string('api_source', 20)->nullable()->after('database');
        });
    }

    public function down(): void
    {
        Schema::table('search_strings', function (Blueprint $table) {
            $table->dropColumn('api_source');
        });
    }
};

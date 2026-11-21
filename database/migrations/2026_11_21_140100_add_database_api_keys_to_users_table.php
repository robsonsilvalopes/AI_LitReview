<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API keys for running search strings on IEEE Xplore, Scopus and PubMed, stored encrypted.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ieee_api_key')->nullable()->after('core_api_key');
            $table->text('scopus_api_key')->nullable()->after('ieee_api_key');
            $table->text('scopus_insttoken')->nullable()->after('scopus_api_key');
            $table->text('pubmed_api_key')->nullable()->after('scopus_insttoken');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ieee_api_key', 'scopus_api_key', 'scopus_insttoken', 'pubmed_api_key']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional publication date range of the search, set with the research question framework
     * and added to the search string drafts.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('search_date_limit')->default(false);
            $table->date('search_date_from')->nullable();
            $table->date('search_date_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['search_date_limit', 'search_date_from', 'search_date_to']);
        });
    }
};

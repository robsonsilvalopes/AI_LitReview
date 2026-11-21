<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CORE (core.ac.uk) API key used by the open-access PDF search, stored encrypted.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('core_api_key')->nullable()->after('unpaywall_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('core_api_key');
        });
    }
};

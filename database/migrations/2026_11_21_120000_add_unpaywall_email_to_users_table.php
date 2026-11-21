<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contact e-mail the Unpaywall API requires, used by the open-access PDF search.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('unpaywall_email')->nullable()->after('ollama_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('unpaywall_email');
        });
    }
};

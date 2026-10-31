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
        Schema::table('projects', function (Blueprint $table) {
            $table->text('objetivo')->nullable()->after('description');
            $table->string('research_time_interval')->nullable()->after('objetivo');
            $table->string('area')->nullable()->after('research_time_interval');
            $table->string('type_of_publication')->nullable()->after('area');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['objetivo', 'research_time_interval', 'area', 'type_of_publication']);
        });
    }
};

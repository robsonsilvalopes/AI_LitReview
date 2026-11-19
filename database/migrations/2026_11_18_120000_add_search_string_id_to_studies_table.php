<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link each imported study to the search string (database) its BibTeX/RIS file came from.
     */
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->foreignId('search_string_id')->nullable()->after('source')
                ->constrained('search_strings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('search_string_id');
        });
    }
};

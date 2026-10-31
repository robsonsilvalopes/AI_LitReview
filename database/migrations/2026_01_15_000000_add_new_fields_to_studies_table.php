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
        Schema::table('studies', function (Blueprint $table) {
            $table->integer('year')->nullable()->after('name');
            $table->text('authors')->nullable()->after('year');
            $table->string('document_type')->nullable()->after('authors');
            $table->string('journal_conference')->nullable()->after('document_type');
            $table->text('tags')->nullable()->after('journal_conference');
            $table->text('notes')->nullable()->after('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropColumn(['year', 'authors', 'document_type', 'journal_conference', 'tags', 'notes']);
        });
    }
};

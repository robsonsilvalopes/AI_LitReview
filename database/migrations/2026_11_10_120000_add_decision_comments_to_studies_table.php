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
            $table->text('comentario_selecao_inicial')->nullable()->after('status_selecao_inicial');
            $table->text('comentario_selecao_final')->nullable()->after('status_selecao_final');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropColumn(['comentario_selecao_inicial', 'comentario_selecao_final']);
        });
    }
};

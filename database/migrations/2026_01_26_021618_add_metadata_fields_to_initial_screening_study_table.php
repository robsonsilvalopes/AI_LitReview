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
        Schema::table('initial_screening_study', function (Blueprint $table) {
            $table->bigInteger('total_duration')->nullable()->after('output_ai')->comment('Duration in milliseconds between request and response');
            $table->integer('input_tokens')->nullable()->after('total_duration')->comment('Number of tokens sent to the model');
            $table->integer('output_tokens')->nullable()->after('input_tokens')->comment('Number of tokens in the response');
            $table->integer('total_tokens')->nullable()->after('output_tokens')->comment('Total tokens used');
            $table->decimal('cost', 10, 8)->nullable()->default(0)->after('total_tokens')->comment('Total cost (0 for local AI)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('initial_screening_study', function (Blueprint $table) {
            $table->dropColumn(['total_duration', 'input_tokens', 'output_tokens', 'total_tokens', 'cost']);
        });
    }
};

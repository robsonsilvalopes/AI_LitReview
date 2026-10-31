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
        // Check if column already exists
        if (!Schema::hasColumn('projects', 'user_id')) {
            Schema::table('projects', function (Blueprint $table) {
                // First add column as nullable
                $table->foreignId('user_id')->nullable()->after('id');
            });
        }
        
        // Update existing projects to assign them to the first user (or delete them)
        // For now, we'll assign them to user_id 1 if it exists
        if (DB::table('users')->exists()) {
            $firstUserId = DB::table('users')->first()->id;
            DB::table('projects')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        } else {
            // If no users exist, delete all projects
            DB::table('projects')->delete();
        }
        
        // Now make it required and add foreign key if it doesn't exist
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'user_id')) {
                // Check if foreign key already exists
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'projects' 
                    AND COLUMN_NAME = 'user_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                if (empty($foreignKeys)) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                }
                
                // Make it not nullable
                $table->foreignId('user_id')->nullable(false)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add all existing project owners as collaborators
        $projects = DB::table('projects')->whereNotNull('user_id')->get();
        
        foreach ($projects as $project) {
            // Check if the owner is already a collaborator
            $exists = DB::table('project_collaborators')
                ->where('project_id', $project->id)
                ->where('user_id', $project->user_id)
                ->exists();
            
            // If not, add them
            if (!$exists) {
                DB::table('project_collaborators')->insert([
                    'project_id' => $project->id,
                    'user_id' => $project->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove project owners from collaborators (optional - you might want to keep them)
        // This is left empty as removing owners might not be desired
    }
};

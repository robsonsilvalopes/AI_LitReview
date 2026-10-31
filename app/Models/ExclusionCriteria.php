<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExclusionCriteria extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'custom_id',
        'exclusion_criteria',
    ];

    /**
     * Get the project that owns the exclusion criteria.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the studies for the exclusion criteria.
     */
    public function studies(): BelongsToMany
    {
        return $this->belongsToMany(Study::class, 'study_exclusion_criteria')
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Get studies by type.
     */
    public function studiesByType(string $type): BelongsToMany
    {
        return $this->belongsToMany(Study::class, 'study_exclusion_criteria')
            ->wherePivot('type', $type)
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Generate the next sequential ID for a project (EC1, EC2, EC3...)
     */
    public static function generateNextCustomId(int $projectId): string
    {
        // Get the highest numeric part from existing custom_ids for this project
        $lastCriteria = self::where('project_id', $projectId)
            ->whereNotNull('custom_id')
            ->where('custom_id', 'like', 'EC%')
            ->orderByRaw('CAST(SUBSTRING(custom_id, 3) AS UNSIGNED) DESC')
            ->first();

        if ($lastCriteria && preg_match('/^EC(\d+)$/', $lastCriteria->custom_id, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'EC' . $nextNumber;
    }
}

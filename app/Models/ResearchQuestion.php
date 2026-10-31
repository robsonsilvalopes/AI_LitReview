<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'custom_id',
        'research_question',
        'answer',
    ];

    /**
     * Get the project that owns the research question.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Generate the next sequential ID for a project (RQ1, RQ2, RQ3...)
     */
    public static function generateNextCustomId(int $projectId): string
    {
        // Get the highest numeric part from existing custom_ids for this project
        $lastQuestion = self::where('project_id', $projectId)
            ->whereNotNull('custom_id')
            ->where('custom_id', 'like', 'RQ%')
            ->orderByRaw('CAST(SUBSTRING(custom_id, 3) AS UNSIGNED) DESC')
            ->first();

        if ($lastQuestion && preg_match('/^RQ(\d+)$/', $lastQuestion->custom_id, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'RQ' . $nextNumber;
    }
}

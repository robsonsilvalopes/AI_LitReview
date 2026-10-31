<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtractionData extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'custom_id',
        'name',
        'value',
        'note',
    ];

    /**
     * Get the project that owns the extraction data.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the studies that have values for this extraction data.
     */
    public function studies()
    {
        return $this->belongsToMany(Study::class, 'study_extraction_data')
            ->withPivot('value')
            ->withTimestamps();
    }

    /**
     * Generate the next sequential ID for a project (ED1, ED2, ED3...)
     */
    public static function generateNextCustomId(int $projectId): string
    {
        // Get all extraction data for this project with custom_id starting with 'ED'
        $extractionData = self::where('project_id', $projectId)
            ->whereNotNull('custom_id')
            ->where('custom_id', 'like', 'ED%')
            ->get();

        $maxNumber = 0;
        foreach ($extractionData as $data) {
            if (preg_match('/^ED(\d+)$/', $data->custom_id, $matches)) {
                $number = (int)$matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        return 'ED' . ($maxNumber + 1);
    }
}

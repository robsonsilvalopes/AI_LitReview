<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Study extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'custom_id',
        'name',
        'year',
        'authors',
        'document_type',
        'journal_conference',
        'tags',
        'notes',
        'doi',
        'keywords',
        'source',
        'search_string_id',
        'abstract',
        'link',
        'registration_method',
        'status_duplicados_exclusao',
        'status_selecao_inicial',
        'status_selecao_final',
        'status_extracao',
        'pdf_path',
    ];

    protected $casts = [
        'status_duplicados_exclusao' => 'boolean',
        'status_selecao_inicial' => 'boolean',
        'status_selecao_final' => 'boolean',
        'status_extracao' => 'boolean',
    ];

    /**
     * Get the project that owns the study.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The search string (database) the study was imported from, when imported from BibTeX/RIS.
     */
    public function searchString(): BelongsTo
    {
        return $this->belongsTo(SearchString::class);
    }

    /**
     * Get the inclusion criterias for the study.
     */
    public function inclusionCriterias(): BelongsToMany
    {
        return $this->belongsToMany(InclusionCriteria::class, 'study_inclusion_criteria')
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Get inclusion criterias by type.
     */
    public function inclusionCriteriasByType(string $type): BelongsToMany
    {
        return $this->belongsToMany(InclusionCriteria::class, 'study_inclusion_criteria')
            ->wherePivot('type', $type)
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Get the exclusion criterias for the study.
     */
    public function exclusionCriterias(): BelongsToMany
    {
        return $this->belongsToMany(ExclusionCriteria::class, 'study_exclusion_criteria')
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Get exclusion criterias by type.
     */
    public function exclusionCriteriasByType(string $type): BelongsToMany
    {
        return $this->belongsToMany(ExclusionCriteria::class, 'study_exclusion_criteria')
            ->wherePivot('type', $type)
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Get inclusion criterias by screening.
     */
    public function inclusionCriteriasByScreening(int $screeningId): BelongsToMany
    {
        return $this->belongsToMany(InclusionCriteria::class, 'study_inclusion_criteria_screening')
            ->wherePivot('initial_screening_id', $screeningId)
            ->withPivot('initial_screening_id')
            ->withTimestamps();
    }

    /**
     * Get exclusion criterias by screening.
     */
    public function exclusionCriteriasByScreening(int $screeningId): BelongsToMany
    {
        return $this->belongsToMany(ExclusionCriteria::class, 'study_exclusion_criteria_screening')
            ->wherePivot('initial_screening_id', $screeningId)
            ->withPivot('initial_screening_id')
            ->withTimestamps();
    }

    /**
     * Get the initial screenings for this study.
     */
    public function initialScreenings()
    {
        return $this->belongsToMany(InitialScreening::class, 'initial_screening_study')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * Get the final screenings for this study.
     */
    public function finalScreenings()
    {
        return $this->belongsToMany(FinalScreening::class, 'final_screening_study')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * Get the extractions for this study.
     */
    public function extractions()
    {
        return $this->belongsToMany(Extraction::class, 'extraction_study')
            ->withPivot('input_ai', 'output_ai', 'total_duration', 'input_tokens', 'output_tokens', 'total_tokens', 'cost')
            ->withTimestamps();
    }

    /**
     * Get the extraction data values for this study.
     */
    public function extractionDataValues()
    {
        return $this->belongsToMany(ExtractionData::class, 'study_extraction_data')
            ->withPivot('value')
            ->withTimestamps();
    }

    /**
     * Get inclusion criterias by final screening.
     */
    public function inclusionCriteriasByFinalScreening(int $screeningId): BelongsToMany
    {
        return $this->belongsToMany(InclusionCriteria::class, 'study_inclusion_criteria_screening')
            ->wherePivot('final_screening_id', $screeningId)
            ->withPivot('final_screening_id')
            ->withTimestamps();
    }

    /**
     * Get exclusion criterias by final screening.
     */
    public function exclusionCriteriasByFinalScreening(int $screeningId): BelongsToMany
    {
        return $this->belongsToMany(ExclusionCriteria::class, 'study_exclusion_criteria_screening')
            ->wherePivot('final_screening_id', $screeningId)
            ->withPivot('final_screening_id')
            ->withTimestamps();
    }

    /**
     * For a record excluded as a duplicate, the record of the same work (same DOI,
     * or same title when there is no DOI) that was kept for screening.
     */
    public function keptDuplicate(): ?self
    {
        if ($this->status_duplicados_exclusao !== false) {
            return null;
        }

        $kept = self::where('project_id', $this->project_id)->where('status_duplicados_exclusao', true);
        $doi = trim((string) $this->doi);

        return in_array(strtolower($doi), ['', 'n/a'], true)
            ? $kept->where('name', $this->name)->first()
            : $kept->where('doi', $doi)->first();
    }

    /**
     * Generate the next sequential ID for a project (S1, S2, S3...)
     */
    public static function generateNextCustomId(int $projectId): string
    {
        // Get all studies for this project with custom_id starting with 'S'
        $studies = self::where('project_id', $projectId)
            ->whereNotNull('custom_id')
            ->where('custom_id', 'like', 'S%')
            ->get();

        $maxNumber = 0;
        foreach ($studies as $study) {
            if (preg_match('/^S(\d+)$/', $study->custom_id, $matches)) {
                $number = (int)$matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        return 'S' . ($maxNumber + 1);
    }
}

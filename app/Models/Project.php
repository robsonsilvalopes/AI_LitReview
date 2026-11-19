<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'objetivo',
        'research_time_interval',
        'area',
        'type_of_publication',
        'user_id',
    ];

    /**
     * Get the user that owns the project.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the studies for the project.
     */
    public function studies()
    {
        return $this->hasMany(Study::class);
    }

    /**
     * Get the search strings for the project.
     */
    public function searchStrings()
    {
        return $this->hasMany(SearchString::class);
    }

    /**
     * Database names to filter studies by: those registered with the search strings plus any
     * other source already stored on the project's studies (manual or Excel entries), sorted.
     */
    public function studyDatabases(): array
    {
        $names = $this->searchStrings()->pluck('database')
            ->merge($this->studies()->whereNull('search_string_id')->whereNotNull('source')
                ->where('source', '!=', '')->distinct()->pluck('source'))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values();

        return $names->all();
    }

    /**
     * Get the research questions for the project.
     */
    public function researchQuestions()
    {
        return $this->hasMany(ResearchQuestion::class);
    }

    /**
     * Get the inclusion criterias for the project.
     */
    public function inclusionCriterias()
    {
        return $this->hasMany(InclusionCriteria::class);
    }

    /**
     * Get the exclusion criterias for the project.
     */
    public function exclusionCriterias()
    {
        return $this->hasMany(ExclusionCriteria::class);
    }

    /**
     * Get the collaborators (users) for the project.
     */
    public function collaborators()
    {
        return $this->belongsToMany(User::class, 'project_collaborators')
                    ->withTimestamps();
    }

    /**
     * Get the initial screenings for the project.
     */
    public function initialScreenings()
    {
        return $this->hasMany(InitialScreening::class);
    }

    /**
     * Get the final screenings for the project.
     */
    public function finalScreenings()
    {
        return $this->hasMany(FinalScreening::class);
    }

    /**
     * Get the extraction data for the project.
     */
    public function extractionData()
    {
        return $this->hasMany(ExtractionData::class);
    }

    /**
     * Get the extractions for the project.
     */
    public function extractions()
    {
        return $this->hasMany(Extraction::class);
    }

    /**
     * Retrieve the child model for bound value.
     * This enables scoped route model binding for nested resources.
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return match($childType) {
            'initialScreening' => $this->initialScreenings()->where($field ?? 'id', $value)->first(),
            'finalScreening' => $this->finalScreenings()->where($field ?? 'id', $value)->first(),
            'extraction' => $this->extractions()->where($field ?? 'id', $value)->first(),
            'study' => $this->studies()->where($field ?? 'id', $value)->first(),
            default => null,
        };
    }
}

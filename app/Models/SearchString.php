<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchString extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'database',
        'api_source',
        'string',
    ];

    /** Database APIs a search string can run on, by api_source value. */
    public const API_SOURCES = [
        'ieee' => 'IEEE Xplore',
        'scopus' => 'Scopus',
        'openalex' => 'OpenAlex',
        'pubmed' => 'PubMed',
    ];

    /**
     * Get the project that owns the search string.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Studies imported from this search string's database.
     */
    public function studies(): HasMany
    {
        return $this->hasMany(Study::class);
    }
}

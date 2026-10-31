<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyExtractionData extends Model
{
    use HasFactory;

    protected $table = 'study_extraction_data';

    protected $fillable = [
        'study_id',
        'extraction_data_id',
        'extraction_id',
        'value',
    ];

    /**
     * Get the study that owns this extraction data value.
     */
    public function study()
    {
        return $this->belongsTo(Study::class);
    }

    /**
     * Get the extraction data definition.
     */
    public function extractionData()
    {
        return $this->belongsTo(ExtractionData::class);
    }

    /**
     * Get the extraction that this data belongs to.
     */
    public function extraction()
    {
        return $this->belongsTo(Extraction::class);
    }
}

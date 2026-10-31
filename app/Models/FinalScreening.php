<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinalScreening extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'nome',
        'tipo',
        'ai_model_id',
        'template_id',
        'responsavel_id',
        'data',
    ];

    protected $casts = [
        'data' => 'date',
    ];

    /**
     * Get the project that owns the final screening.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the AI model used for the screening (if applicable).
     */
    public function aiModel()
    {
        return $this->belongsTo(AvailableModel::class, 'ai_model_id');
    }

    /**
     * Get the template used for the screening (if applicable).
     */
    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    /**
     * Get the user responsible for the screening.
     */
    public function responsavel()
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    /**
     * Get the studies for this screening.
     */
    public function studies()
    {
        return $this->belongsToMany(Study::class, 'final_screening_study')
            ->withPivot('status', 'input_ai', 'output_ai', 'total_duration', 'input_tokens', 'output_tokens', 'total_tokens', 'cost')
            ->withTimestamps();
    }
}

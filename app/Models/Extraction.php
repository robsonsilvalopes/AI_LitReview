<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extraction extends Model
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

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function aiModel()
    {
        return $this->belongsTo(AvailableModel::class, 'ai_model_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function responsavel()
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function studies()
    {
        return $this->belongsToMany(Study::class, 'extraction_study')
            ->withPivot('input_ai', 'output_ai', 'total_duration', 'input_tokens', 'output_tokens', 'total_tokens', 'cost')
            ->withTimestamps();
    }
}

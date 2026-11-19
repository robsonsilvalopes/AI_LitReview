<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Extraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'nome',
        'tipo',
        'ai_model_id',
        'template_id',
        'temperature',
        'responsavel_id',
        'data',
    ];

    protected $casts = [
        'data' => 'date',
        'temperature' => 'float',
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

    /**
     * Studies this extraction covers: the project's studies included in the final selection.
     */
    public function eligibleStudies()
    {
        return Study::where('project_id', $this->project_id)
            ->where('status_selecao_final', 1);
    }

    /**
     * Eligible studies the AI has not extracted yet (no output saved). Extracted studies are
     * left out so that continuing neither pays for them twice nor overwrites their results.
     */
    public function pendingStudies()
    {
        return $this->eligibleStudies()
            ->whereNotIn('id', DB::table('extraction_study')
                ->where('extraction_id', $this->id)
                ->whereNotNull('output_ai')
                ->select('study_id'));
    }

    public function studies()
    {
        return $this->belongsToMany(Study::class, 'extraction_study')
            ->withPivot('input_ai', 'output_ai', 'total_duration', 'input_tokens', 'output_tokens', 'total_tokens', 'cost')
            ->withTimestamps();
    }
}

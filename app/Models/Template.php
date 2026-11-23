<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'name',
        'template',
        'type',
    ];

    /**
     * The project the template belongs to (null only for templates created before templates
     * belonged to projects and used by none).
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created the template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

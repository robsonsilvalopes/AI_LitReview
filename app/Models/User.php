<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'openrouter_api_key',
        'ollama_url',
        'unpaywall_email',
        'core_api_key',
        'ieee_api_key',
        'scopus_api_key',
        'scopus_insttoken',
        'pubmed_api_key',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'core_api_key',
        'ieee_api_key',
        'scopus_api_key',
        'scopus_insttoken',
        'pubmed_api_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'core_api_key' => 'encrypted',
            'ieee_api_key' => 'encrypted',
            'scopus_api_key' => 'encrypted',
            'scopus_insttoken' => 'encrypted',
            'pubmed_api_key' => 'encrypted',
        ];
    }

    /**
     * Get the projects for the user.
     */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the projects where the user is a collaborator.
     */
    public function collaboratedProjects()
    {
        return $this->belongsToMany(Project::class, 'project_collaborators')
                    ->withTimestamps();
    }
}
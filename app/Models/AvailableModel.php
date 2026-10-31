<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailableModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'model_id',
        'name',
        'model_json',
        'price_input',
        'price_output',
        'type',
    ];

    protected $casts = [
        'model_json' => 'array',
        'price_input' => 'float',
        'price_output' => 'float',
    ];
}

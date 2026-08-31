<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'aliases',
        'price',
        'is_active',
    ];

    protected $casts = [
        'aliases' => 'array',
        'price' => 'integer',
        'is_active' => 'boolean',
    ];
}

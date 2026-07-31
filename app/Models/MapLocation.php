<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapLocation extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'position_top',
        'position_left',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'position_top'  => 'float',
            'position_left' => 'float',
            'is_active'     => 'boolean',
        ];
    }
}

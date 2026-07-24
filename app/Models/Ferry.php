<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ferry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'capacity',
        'price_per_seat',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_per_seat' => 'decimal:2',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(FerrySchedule::class);
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(FerryScheduleTemplate::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FerryScheduleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'ferry_id',
        'frequency',
        'weekdays',
        'day_of_month',
        'departure_time',
        'arrival_time',
        'available_seats',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function ferry(): BelongsTo
    {
        return $this->belongsTo(Ferry::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(FerrySchedule::class, 'template_id');
    }
}

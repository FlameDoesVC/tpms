<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSlotTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'frequency',
        'weekdays',
        'day_of_month',
        'slot_time',
        'available_capacity',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(ThemeParkEvent::class, 'event_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(EventSlot::class, 'template_id');
    }
}

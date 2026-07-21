<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'slot_date',
        'slot_time',
        'available_capacity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ThemeParkEvent::class, 'event_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventBooking::class, 'event_slot_id');
    }
}

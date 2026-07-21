<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ThemeParkEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'location',
        'duration_minutes',
        'capacity_per_slot',
        'price_per_ticket',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_ticket' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(EventSlot::class, 'event_id');
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(EventBooking::class, EventSlot::class, 'event_id', 'event_slot_id');
    }
}

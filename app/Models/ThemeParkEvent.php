<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ThemeParkEvent extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'type',
        'location',
        'duration_minutes',
        'capacity_per_slot',
        'price_per_ticket',
        'is_active',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price_per_ticket' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->getFirstMediaUrl('image') ?: null);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(EventSlot::class, 'event_id');
    }

    public function slotTemplates(): HasMany
    {
        return $this->hasMany(EventSlotTemplate::class, 'event_id');
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(EventBooking::class, EventSlot::class, 'event_id', 'event_slot_id');
    }
}

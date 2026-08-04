<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ThemeParkEvent extends Model implements HasMedia
{
    use Concerns\HasSanitisedImage;
    use Concerns\HasVisibilityScope;
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

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerSanitisedImageConversion();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    // imageUrl() and the sanitising conversion come from HasSanitisedImage.

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

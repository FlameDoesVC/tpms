<?php

namespace App\Models;

use App\Models\Concerns\HasVisibilityScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Hotel extends Model implements HasMedia
{
    use Concerns\HasSanitisedImage;
    use HasFactory;
    use HasVisibilityScope;
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'address',
        'facilities',
        'check_in_time',
        'check_out_time',
        'phone',
        'email',
        'website',
        'total_rooms',
        'is_active',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Unscoped on purpose - see the note in RoomType.
        $this->registerSanitisedImageConversion();
    }

    public function registerMediaCollections(): void
    {
        $mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes($mimes);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes($mimes);
    }

    // imageUrl(), galleryItems() and the sanitising conversion come from HasSanitisedImage.

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, Room::class);
    }
}

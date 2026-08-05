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
        'total_rooms',
        'is_active',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
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

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, Room::class);
    }
}

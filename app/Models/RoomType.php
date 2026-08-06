<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A bookable category of room - what a guest actually chooses ("Ocean Suite",
 * $250, sleeps 4, sea view). Individual Rooms are the physical inventory behind
 * it; a booking still holds a specific room, but nothing in the browsing flow
 * exposes room numbers.
 */
class RoomType extends Model implements HasMedia
{
    use Concerns\HasSanitisedImage;
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'price_per_night',
        'max_guests',
        'amenities',
        'is_active',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price_per_night' => 'decimal:2',
            'amenities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Left unscoped so gallery images are sanitised on the same terms as
        // the cover; performOnCollections('image') here would serve gallery
        // uploads as raw bytes.
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

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * How many rooms of this type are free for the given stay - one query, no
     * per-room round trip. Passing no dates counts rooms that are merely in
     * service.
     *
     * The overlap predicate is deliberately identical to
     * Room::isAvailableBetween(), which the booking transaction still uses under
     * a row lock; the two must agree or a guest sees availability that vanishes
     * at checkout.
     */
    public function availableRoomCountBetween(?string $checkIn = null, ?string $checkOut = null): int
    {
        return $this->rooms()
            ->where('is_available', true)
            ->when($checkIn && $checkOut, fn ($query) => $query->whereDoesntHave('bookings', fn ($bookings) => $bookings
                ->where('status', '!=', 'cancelled')
                ->where('check_in_date', '<', $checkOut)
                ->where('check_out_date', '>', $checkIn)))
            ->count();
    }
}

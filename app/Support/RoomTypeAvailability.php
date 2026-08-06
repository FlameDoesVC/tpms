<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Collection;

/**
 * How many rooms of each type a hotel can still let for a given stay.
 *
 * One grouped query for the whole hotel. The browse pages ask this for every
 * room type on every date change, and the previous implementation ran an
 * existence check per room.
 */
class RoomTypeAvailability
{
    /**
     * @return array<int, int> room_type_id => available room count
     */
    public static function forHotel(Hotel $hotel, ?string $checkIn = null, ?string $checkOut = null): array
    {
        return Room::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_available', true)
            ->when($checkIn && $checkOut, fn ($query) => $query->whereDoesntHave('bookings', fn ($bookings) => $bookings
                // Same overlap predicate as Room::isAvailableBetween(), which the
                // booking transaction re-checks under a row lock. If these two
                // ever disagree, a guest is shown rooms that vanish at checkout.
                ->where('status', '!=', 'cancelled')
                ->where('check_in_date', '<', $checkOut)
                ->where('check_out_date', '>', $checkIn)))
            ->selectRaw('room_type_id, COUNT(*) as aggregate_count')
            ->groupBy('room_type_id')
            ->pluck('aggregate_count', 'room_type_id')
            ->all();
    }

    /**
     * Stamps `available_count` onto each room type so the resource can expose it.
     * Types with nothing free are reported as 0 rather than dropped - a detail
     * page says "sold out", it does not hide the room.
     *
     * @param  Collection<int, RoomType>  $roomTypes
     */
    public static function attach(Collection $roomTypes, array $counts): void
    {
        $roomTypes->each(function ($roomType) use ($counts) {
            $roomType->available_count = $counts[$roomType->id] ?? 0;
        });
    }
}

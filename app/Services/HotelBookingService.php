<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HotelBookingService
{
    /**
     * Creates `quantity` bookings for a party, splitting guests evenly across
     * sibling rooms of the same type/price/capacity. Callers must run this
     * inside a DB transaction - the availability check below relies on
     * lockForUpdate() to mean anything.
     */
    public function create(int $userId, array $data): Collection
    {
        $quantity = $data['quantity'] ?? 1;
        $representative = Room::findOrFail($data['room_id']);

        if ($data['guests_count'] > $representative->max_guests * $quantity) {
            throw ValidationException::withMessages([
                'guests_count' => 'These rooms only fit '.($representative->max_guests * $quantity).' guests total - increase the room quantity.',
            ]);
        }

        $nights = Carbon::parse($data['check_in_date'])->diffInDays(Carbon::parse($data['check_out_date']));

        $candidates = Room::where('hotel_id', $representative->hotel_id)
            ->where('type', $representative->type)
            ->where('price_per_night', $representative->price_per_night)
            ->where('max_guests', $representative->max_guests)
            ->where('is_available', true)
            ->lockForUpdate()
            ->get()
            ->filter(fn (Room $room) => $room->isAvailableBetween($data['check_in_date'], $data['check_out_date']))
            ->values();

        if ($candidates->count() < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Only '.$candidates->count().' room(s) of this type are available for these dates.',
            ]);
        }

        $selected = $candidates->take($quantity)->values();
        $baseGuests = intdiv($data['guests_count'], $quantity);
        $extraGuests = $data['guests_count'] % $quantity;

        $created = $selected->map(fn (Room $room, int $i) => Booking::create([
            'user_id' => $userId,
            'room_id' => $room->id,
            'check_in_date' => $data['check_in_date'],
            'check_out_date' => $data['check_out_date'],
            'guests_count' => $baseGuests + ($i < $extraGuests ? 1 : 0),
            'total_price' => $nights * $room->price_per_night,
            'status' => 'pending',
        ]))->values();

        // Link the sibling rooms of one multi-room purchase together so their
        // guest counts can be summed as one party's total capacity later (e.g.
        // when validating a ferry ticket against the whole group, not just
        // whichever single room the ticket happens to reference).
        if ($quantity > 1) {
            $anchorId = $created->first()->id;
            $created->skip(1)->each(fn (Booking $booking) => $booking->update(['group_booking_id' => $anchorId]));
        }

        return $created;
    }

    public function confirm(Collection $bookings): void
    {
        foreach ($bookings as $booking) {
            $booking->update(['status' => 'confirmed']);
        }
    }
}

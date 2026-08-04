<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    /**
     * The single owner of the pending -> confirmed transition.
     *
     * Every path that settles a booking goes through here - the visitor paying on
     * the confirmation screen, and cart checkout - so a confirmed booking always
     * has a payment behind it. The amount is read from the booking, never from
     * the request: the client computes a total for display only.
     *
     * Callers must run this inside a DB transaction.
     */
    public function settle(Collection $bookings, User $actor, string $method = 'card'): Collection
    {
        return $bookings->map(function (Booking $booking) use ($actor, $method) {
            Payment::create([
                'user_id' => $booking->user_id,
                'payable_type' => $booking->getMorphClass(),
                'payable_id' => $booking->id,
                'amount' => $booking->total_price,
                'method' => $method,
                'status' => 'captured',
                'reference' => 'PAY-'.Str::upper(Str::random(12)),
                'recorded_by' => $actor->id,
            ]);

            $booking->update(['status' => 'confirmed']);

            return $booking->fresh();
        })->values();
    }
}

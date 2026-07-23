<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FerryTicketService
{
    /**
     * Issues one ticket per seat number for a confirmed hotel booking.
     * Callers must run this inside a DB transaction - the seat-conflict
     * check below relies on lockForUpdate() to mean anything.
     */
    public function issue(int $userId, array $data): Collection
    {
        $booking = Booking::findOrFail($data['booking_id']);

        if ($booking->user_id !== $userId || $booking->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'booking_id' => 'You need a confirmed hotel booking to purchase a ferry ticket.',
            ]);
        }

        $partyCapacity = $booking->partyGuestsCount();

        if (count($data['seat_numbers']) > $partyCapacity) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'Your party is '.$partyCapacity.' guest(s) - you can\'t select more seats than that.',
            ]);
        }

        $schedule = FerrySchedule::lockForUpdate()->findOrFail($data['schedule_id']);
        $seatCount = count($data['seat_numbers']);

        // One ticket per leg per party - a party only makes one trip out and
        // one trip back, so picking a second (different) ferry on the same
        // date as an already-booked one isn't a different leg, it's a
        // duplicate of the same one.
        $partyBookingIds = $booking->partyBookingIds();
        $alreadyBookedForThisDate = FerryTicket::whereIn('booking_id', $partyBookingIds)
            ->whereHas('schedule', fn ($query) => $query->whereDate('departure_date', $schedule->departure_date))
            ->exists();

        if ($alreadyBookedForThisDate) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Your party already has a ferry ticket for this date.',
            ]);
        }

        if ($schedule->available_seats < $seatCount) {
            throw ValidationException::withMessages([
                'schedule_id' => 'This departure does not have enough seats left.',
            ]);
        }

        if (max($data['seat_numbers']) > $schedule->ferry->capacity) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'Seat number exceeds this ferry\'s capacity.',
            ]);
        }

        $taken = $schedule->tickets()
            ->whereIn('status', ['pending', 'issued', 'used'])
            ->whereIn('seat_number', $data['seat_numbers'])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'One or more selected seats have already been taken.',
            ]);
        }

        $schedule->decrement('available_seats', $seatCount);
        $price = $schedule->ferry->price_per_seat;

        return collect($data['seat_numbers'])->map(fn ($seatNumber) => FerryTicket::create([
            'user_id' => $userId,
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_number' => $seatNumber,
            'status' => 'issued',
            'price' => $price,
            'payment_method' => $data['payment_method'],
        ]))->values();
    }
}

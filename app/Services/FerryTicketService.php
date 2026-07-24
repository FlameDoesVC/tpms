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
     * Issues one ticket per seat number for a confirmed hotel booking. The
     * ticket always belongs to the BOOKING's owner, not necessarily whoever
     * is calling - a ferry_operator issuing a walk-up ticket on a visitor's
     * behalf passes that visitor's id here, not their own; the caller is
     * responsible for deciding who that should be and for authorizing the
     * request itself (this method only checks the booking is actually
     * confirmed and belongs to the id given). Callers must run this inside a
     * DB transaction - the seat-conflict check below relies on
     * lockForUpdate() to mean anything.
     */
    public function issue(int $ticketOwnerId, array $data): Collection
    {
        $booking = Booking::findOrFail($data['booking_id']);

        if ($booking->user_id !== $ticketOwnerId || $booking->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'booking_id' => 'You need a confirmed hotel booking to purchase a ferry ticket.',
            ]);
        }

        $schedule = FerrySchedule::lockForUpdate()->findOrFail($data['schedule_id']);
        $seatCount = count($data['seat_numbers']);

        // A party only makes one trip per leg, but can top up across more
        // than one purchase - e.g. some of the party booked online ahead of
        // time and the rest pay cash walking up to the gate. What matters is
        // that everyone ticketed for this date, across however many
        // purchases, never exceeds the party's total headcount - not that
        // this single purchase is the only one.
        $partyCapacity = $booking->partyGuestsCount();
        $partyBookingIds = $booking->partyBookingIds();
        $alreadyIssuedForThisDate = FerryTicket::whereIn('booking_id', $partyBookingIds)
            ->whereHas('schedule', fn ($query) => $query->whereDate('departure_date', $schedule->departure_date))
            ->whereIn('status', ['pending', 'issued', 'used'])
            ->count();
        $remainingCapacity = $partyCapacity - $alreadyIssuedForThisDate;

        if ($seatCount > $remainingCapacity) {
            $message = $remainingCapacity > 0
                ? "Your party has {$alreadyIssuedForThisDate} of {$partyCapacity} seat(s) already booked for this date - you can book at most {$remainingCapacity} more."
                : "Your party has already booked all {$partyCapacity} seat(s) for this date.";

            throw ValidationException::withMessages(['seat_numbers' => $message]);
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
            'user_id' => $ticketOwnerId,
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_number' => $seatNumber,
            'status' => 'issued',
            'price' => $price,
            'payment_method' => $data['payment_method'],
        ]))->values();
    }
}

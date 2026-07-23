<?php

namespace App\Services;

use App\Models\EventBooking;
use App\Models\EventSlot;
use Illuminate\Validation\ValidationException;

class ThemeParkBookingService
{
    /**
     * Callers must run this inside a DB transaction - the capacity check
     * below relies on lockForUpdate() to mean anything.
     */
    public function book(int $userId, array $data): EventBooking
    {
        $slot = EventSlot::lockForUpdate()->findOrFail($data['event_slot_id']);

        if ($slot->available_capacity < $data['ticket_count']) {
            throw ValidationException::withMessages([
                'ticket_count' => 'Not enough capacity left for this slot.',
            ]);
        }

        $slot->decrement('available_capacity', $data['ticket_count']);

        return EventBooking::create([
            'user_id' => $userId,
            'event_slot_id' => $slot->id,
            'ticket_count' => $data['ticket_count'],
            'status' => 'confirmed',
        ]);
    }
}

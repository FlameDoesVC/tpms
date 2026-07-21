<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventBooking;
use App\Models\EventSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThemeParkTicketController extends Controller
{
    public function sellTicket(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('themepark_staff')) {
            abort(403);
        }

        $validated = $request->validate([
            'event_slot_id' => ['required', 'exists:event_slots,id'],
            'ticket_count' => ['required', 'integer', 'min:1'],
            'visitor_name' => ['nullable', 'string', 'max:255'],
        ]);

        $booking = DB::transaction(function () use ($validated) {
            $slot = EventSlot::lockForUpdate()->findOrFail($validated['event_slot_id']);

            if ($slot->available_capacity < $validated['ticket_count']) {
                throw ValidationException::withMessages([
                    'ticket_count' => 'Not enough capacity left for this slot.',
                ]);
            }

            $slot->decrement('available_capacity', $validated['ticket_count']);

            return EventBooking::create([
                'user_id' => null,
                'visitor_name' => $validated['visitor_name'] ?? 'Walk-in',
                'event_slot_id' => $slot->id,
                'ticket_count' => $validated['ticket_count'],
                'status' => 'confirmed',
            ]);
        });

        return response()->json($booking, 201);
    }

    public function showTicket(Request $request, EventBooking $booking): JsonResponse
    {
        if (! $request->user()->hasRole('themepark_staff')) {
            abort(403);
        }

        return response()->json($booking->load(['user', 'slot.event']));
    }

    public function validateTicket(Request $request, EventBooking $booking): JsonResponse
    {
        if (! $request->user()->hasRole('themepark_staff')) {
            abort(403);
        }

        if ($booking->status === 'used') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has already been used.',
            ]);
        }

        $booking->update(['status' => 'used']);

        return response()->json($booking->load('slot.event'));
    }

    public function dailySales(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('themepark_staff')) {
            abort(403);
        }

        $validated = $request->validate(['date' => ['required', 'date']]);

        $rows = DB::table('event_bookings')
            ->join('event_slots', 'event_slots.id', '=', 'event_bookings.event_slot_id')
            ->join('theme_park_events', 'theme_park_events.id', '=', 'event_slots.event_id')
            ->whereDate('event_slots.slot_date', $validated['date'])
            ->where('event_bookings.status', '!=', 'cancelled')
            ->groupBy('theme_park_events.id', 'theme_park_events.name', 'theme_park_events.price_per_ticket')
            ->selectRaw('theme_park_events.id as event_id, theme_park_events.name as event_name, theme_park_events.price_per_ticket as price_per_ticket, SUM(event_bookings.ticket_count) as tickets_sold')
            ->get();

        $report = $rows->map(fn ($row) => [
            'event_id' => $row->event_id,
            'event_name' => $row->event_name,
            'tickets_sold' => (int) $row->tickets_sold,
            'revenue' => number_format($row->tickets_sold * $row->price_per_ticket, 2, '.', ''),
        ]);

        return response()->json($report->values());
    }

    public function capacityStatus(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('themepark_staff')) {
            abort(403);
        }

        $slots = EventSlot::query()
            ->whereDate('slot_date', now()->toDateString())
            ->with('event')
            ->get();

        $data = $slots->map(function (EventSlot $slot) {
            $capacity = $slot->event->capacity_per_slot;
            $booked = $capacity - $slot->available_capacity;

            return [
                'slot_id' => $slot->id,
                'event_name' => $slot->event->name,
                'slot_time' => $slot->slot_time,
                'capacity' => $capacity,
                'booked' => $booked,
                'available' => $slot->available_capacity,
                'fill_percentage' => $capacity > 0 ? (int) round(($booked / $capacity) * 100) : 0,
            ];
        });

        return response()->json($data->values());
    }
}

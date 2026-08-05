<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThemeParkTicketController extends Controller
{
    public function sellTicket(Request $request): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['themepark_staff', 'admin'])) {
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

    /**
     * Resolve a scanned park-ticket code. Previously the scanner parsed the
     * trailing digits into a primary key, which made a guessed code equivalent to
     * a real ticket.
     */
    public function lookupByReference(Request $request): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['themepark_staff', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $booking = EventBooking::findByReferenceCode($validated['code']);

        return response()->json($booking->load(['user:id,name', 'slot.event']));
    }

    public function showTicket(Request $request, EventBooking $booking): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['themepark_staff', 'admin'])) {
            abort(403);
        }

        return response()->json($booking->load(['user:id,name', 'slot.event']));
    }

    public function validateTicket(Request $request, EventBooking $booking): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['themepark_staff', 'admin'])) {
            abort(403);
        }

        if ($booking->status === 'used') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has already been used.',
            ]);
        }

        // A refunded ticket still admitted its holder. Because cancelling had
        // already returned the seat to inventory, every such admission left the
        // slot oversold by exactly the party that walked in.
        if ($booking->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has been cancelled.',
            ]);
        }

        if ($booking->slot->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => 'This time slot is not running.',
            ]);
        }

        // Admission closes an hour after the slot starts - a ticket scanned
        // later than that isn't catching a late arrival, it's someone showing
        // up after the ride or show already happened.
        $startedAt = $booking->slot->slot_date->copy()->setTimeFromTimeString($booking->slot->slot_time);
        if (now()->greaterThan($startedAt->addMinutes(60))) {
            throw ValidationException::withMessages([
                'status' => 'This slot started more than an hour ago and the ticket can no longer be validated.',
            ]);
        }

        $booking->update([
            'status' => 'used',
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ]);

        AuditLog::record('park.ticket.validated', [
            'booking_id' => $booking->id,
            'reference_code' => $booking->reference_code,
            'event_slot_id' => $booking->event_slot_id,
        ], $request);

        return response()->json($booking->load('slot.event'));
    }

    public function dailySales(Request $request): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['themepark_staff', 'admin'])) {
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
        if (! $request->user()->hasAnyRole(['themepark_staff', 'admin'])) {
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\ThemeParkEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ThemeParkController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ThemeParkEvent::query()->where('is_active', true)->get());
    }

    /**
     * Top events by non-cancelled booking count, for the homepage.
     */
    public function popular(): JsonResponse
    {
        $events = ThemeParkEvent::query()
            ->where('is_active', true)
            ->withCount(['bookings' => fn ($query) => $query->where('event_bookings.status', '!=', 'cancelled')])
            ->orderByDesc('bookings_count')
            ->limit(3)
            ->get();

        return response()->json($events);
    }

    public function show(Request $request, ThemeParkEvent $event): JsonResponse
    {
        $validated = $request->validate(['date' => ['nullable', 'date']]);

        $slots = $event->slots();
        if (! empty($validated['date'])) {
            $slots->whereDate('slot_date', $validated['date']);
        }

        $data = $event->toArray();
        $data['slots'] = $slots->get();

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ThemeParkEvent::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:ride,show,beach_event'],
            'location' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'capacity_per_slot' => ['required', 'integer', 'min:1'],
            'price_per_ticket' => ['required', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string'],
        ]);

        $event = ThemeParkEvent::create($validated)->refresh();

        return response()->json($event, 201);
    }

    public function update(Request $request, ThemeParkEvent $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:ride,show,beach_event'],
            'location' => ['sometimes', 'string', 'max:255'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1'],
            'capacity_per_slot' => ['sometimes', 'integer', 'min:1'],
            'price_per_ticket' => ['sometimes', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $event->update($validated);

        return response()->json($event);
    }

    public function destroy(ThemeParkEvent $event): Response
    {
        Gate::authorize('delete', $event);

        $event->delete();

        return response()->noContent();
    }

    public function storeSlot(Request $request, ThemeParkEvent $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $validated = $request->validate([
            'slot_date' => ['required', 'date'],
            'slot_time' => ['required', 'date_format:H:i'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $slot = $event->slots()->create([
            'slot_date' => $validated['slot_date'],
            'slot_time' => $validated['slot_time'],
            'available_capacity' => $validated['capacity'] ?? $event->capacity_per_slot,
            'status' => 'scheduled',
        ])->refresh();

        return response()->json($slot, 201);
    }

    public function slots(Request $request, ThemeParkEvent $event): JsonResponse
    {
        $validated = $request->validate(['date' => ['nullable', 'date']]);

        $query = $event->slots();
        if (! empty($validated['date'])) {
            $query->whereDate('slot_date', $validated['date']);
        }

        return response()->json($query->get());
    }

    public function bookSlot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_slot_id' => ['required', 'exists:event_slots,id'],
            'ticket_count' => ['required', 'integer', 'min:1'],
        ]);

        $booking = DB::transaction(function () use ($validated, $request) {
            $slot = EventSlot::lockForUpdate()->findOrFail($validated['event_slot_id']);

            if ($slot->available_capacity < $validated['ticket_count']) {
                throw ValidationException::withMessages([
                    'ticket_count' => 'Not enough capacity left for this slot.',
                ]);
            }

            $slot->decrement('available_capacity', $validated['ticket_count']);

            return EventBooking::create([
                'user_id' => $request->user()->id,
                'event_slot_id' => $slot->id,
                'ticket_count' => $validated['ticket_count'],
                'status' => 'confirmed',
            ]);
        });

        return response()->json($booking, 201);
    }

    public function myBookings(Request $request): JsonResponse
    {
        $bookings = EventBooking::query()
            ->where('user_id', $request->user()->id)
            ->with('slot.event')
            ->get();

        return response()->json($bookings);
    }

    public function cancelBooking(Request $request, EventBooking $booking): JsonResponse
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id && ! $user->hasRole('themepark_staff')) {
            abort(403);
        }

        DB::transaction(function () use ($booking) {
            EventSlot::lockForUpdate()->findOrFail($booking->event_slot_id)
                ->increment('available_capacity', $booking->ticket_count);
            $booking->update(['status' => 'cancelled']);
        });

        return response()->json($booking->fresh());
    }
}

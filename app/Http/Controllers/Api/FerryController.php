<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FerryController extends Controller
{
    public function ferries(): JsonResponse
    {
        return response()->json(Ferry::query()->where('is_active', true)->get());
    }

    public function schedules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $query = FerrySchedule::query()->with('ferry');

        if (! empty($validated['date'])) {
            $query->whereDate('departure_date', $validated['date']);
        }

        return response()->json($query->orderBy('departure_date')->orderBy('departure_time')->get());
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        Gate::authorize('create', FerrySchedule::class);

        $validated = $request->validate([
            'ferry_id' => ['required', 'exists:ferries,id'],
            'departure_date' => ['required', 'date'],
            'departure_time' => ['required', 'date_format:H:i'],
            'arrival_time' => ['required', 'date_format:H:i', 'after:departure_time'],
        ]);

        $ferry = Ferry::findOrFail($validated['ferry_id']);

        $schedule = FerrySchedule::create([
            ...$validated,
            'available_seats' => $ferry->capacity,
            'status' => 'scheduled',
        ])->refresh();

        return response()->json($schedule, 201);
    }

    public function updateSchedule(Request $request, FerrySchedule $schedule): JsonResponse
    {
        Gate::authorize('update', $schedule);

        $validated = $request->validate([
            'departure_date' => ['sometimes', 'date'],
            'departure_time' => ['sometimes', 'date_format:H:i'],
            'arrival_time' => ['sometimes', 'date_format:H:i'],
            'status' => ['sometimes', 'in:scheduled,departed,cancelled'],
        ]);

        $schedule->update($validated);

        return response()->json($schedule);
    }

    public function destroySchedule(FerrySchedule $schedule): Response
    {
        Gate::authorize('delete', $schedule);

        $schedule->delete();

        return response()->noContent();
    }

    public function issueTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => ['required', 'exists:ferry_schedules,id'],
            'booking_id' => ['required', 'exists:bookings,id'],
        ]);

        $user = $request->user();
        $booking = Booking::findOrFail($validated['booking_id']);

        if ($booking->user_id !== $user->id || $booking->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'booking_id' => 'You need a confirmed hotel booking to purchase a ferry ticket.',
            ]);
        }

        $ticket = DB::transaction(function () use ($validated, $user, $booking) {
            $schedule = FerrySchedule::lockForUpdate()->findOrFail($validated['schedule_id']);

            if ($schedule->available_seats < 1) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'This departure is fully booked.',
                ]);
            }

            $seatNumber = $schedule->ferry->capacity - $schedule->available_seats + 1;
            $schedule->decrement('available_seats');

            return FerryTicket::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->id,
                'booking_id' => $booking->id,
                'seat_number' => $seatNumber,
                'status' => 'issued',
            ]);
        });

        return response()->json($ticket, 201);
    }

    public function myTickets(Request $request): JsonResponse
    {
        $tickets = FerryTicket::query()
            ->where('user_id', $request->user()->id)
            ->with('schedule.ferry')
            ->get();

        return response()->json($tickets);
    }

    public function validateTicket(Request $request, FerryTicket $ticket): JsonResponse
    {
        if (! $request->user()->hasRole('ferry_operator')) {
            abort(403);
        }

        if ($ticket->status === 'used') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has already been used.',
            ]);
        }

        $ticket->update(['status' => 'used']);

        return response()->json($ticket->load(['user', 'schedule.ferry']));
    }

    public function passengers(Request $request, FerrySchedule $schedule): JsonResponse
    {
        if (! $request->user()->hasRole('ferry_operator')) {
            abort(403);
        }

        return response()->json(
            $schedule->tickets()->with(['user', 'booking'])->get()
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use App\Services\FerryTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FerryController extends Controller
{
    public function __construct(private FerryTicketService $tickets) {}

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

    public function seats(FerrySchedule $schedule): JsonResponse
    {
        $takenSeats = $schedule->tickets()
            ->whereIn('status', ['pending', 'issued', 'used'])
            ->pluck('seat_number');

        return response()->json([
            'capacity' => $schedule->ferry->capacity,
            'price_per_seat' => $schedule->ferry->price_per_seat,
            'taken_seats' => $takenSeats,
        ]);
    }

    public function issueTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => ['required', 'exists:ferry_schedules,id'],
            'booking_id' => ['required', 'exists:bookings,id'],
            'seat_numbers' => ['required', 'array', 'min:1'],
            'seat_numbers.*' => ['integer', 'min:1', 'distinct'],
            'payment_method' => ['required', 'in:online,cash'],
        ]);

        $tickets = DB::transaction(fn () => $this->tickets->issue($request->user()->id, $validated));

        return response()->json($tickets, 201);
    }

    public function myTickets(Request $request): JsonResponse
    {
        $tickets = FerryTicket::query()
            ->where('user_id', $request->user()->id)
            ->with(['schedule.ferry', 'booking'])
            ->get();

        return response()->json($tickets);
    }

    public function showTicket(Request $request, FerryTicket $ticket): JsonResponse
    {
        if (! $request->user()->hasRole('ferry_operator')) {
            abort(403);
        }

        return response()->json($ticket->load(['user', 'schedule.ferry', 'booking']));
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

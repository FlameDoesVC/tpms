<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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

    /**
     * Public list: only bookable boats. `?all=1` is for the management screen
     * and needs a signed-in operator, since a retired ferry is not something a
     * visitor should be able to enumerate.
     */
    public function ferries(Request $request): JsonResponse
    {
        $wantsAll = $request->boolean('all');

        if ($wantsAll) {
            Gate::authorize('create', Ferry::class);
        }

        $ferries = Ferry::query()
            ->when(! $wantsAll, fn ($q) => $q->where('is_active', true))
            ->withCount('schedules')
            ->orderBy('name')
            ->get();

        return response()->json($ferries->map(fn (Ferry $ferry) => $this->formatFerry($ferry, $wantsAll)));
    }

    public function storeFerry(Request $request): JsonResponse
    {
        Gate::authorize('create', Ferry::class);

        $validated = $this->validateFerry($request);
        $grid = Ferry::normaliseGrid($validated['layout']['grid'] ?? null);

        $ferry = Ferry::create([
            'name' => $validated['name'],
            'price_per_seat' => $validated['price_per_seat'],
            'is_active' => $validated['is_active'] ?? true,
            // Capacity is never taken from the client: it is however many seat
            // cells the grid holds, so the two can't drift apart.
            'capacity' => Ferry::seatCount($grid),
            'layout' => [
                'grid' => $grid,
                'entrances' => Ferry::normaliseEntrances(
                    $validated['layout']['entrances'] ?? [],
                    count($grid),
                    strlen($grid[0])
                ),
            ],
        ]);

        return response()->json($this->formatFerry($ferry->refresh(), true), 201);
    }

    public function updateFerry(Request $request, Ferry $ferry): JsonResponse
    {
        Gate::authorize('update', $ferry);

        $validated = $this->validateFerry($request);
        $grid = Ferry::normaliseGrid($validated['layout']['grid'] ?? null);
        $capacity = Ferry::seatCount($grid);

        // Shrinking a deck under seats that are already sold would leave live
        // tickets pointing at a seat that no longer exists on the boat. The
        // layout editor warns about this too; this is the authority.
        $highestSold = $this->highestSoldSeat($ferry);
        if ($capacity < $highestSold) {
            throw ValidationException::withMessages([
                'layout' => "Seat {$highestSold} is already sold on this ferry, so the deck can't drop below {$highestSold} seats. Cancel that ticket first, or keep the seat.",
            ]);
        }

        $capacityChanged = (int) $ferry->capacity !== $capacity;

        $ferry->update([
            'name' => $validated['name'],
            'price_per_seat' => $validated['price_per_seat'],
            'is_active' => $validated['is_active'] ?? $ferry->is_active,
            'capacity' => $capacity,
            'layout' => [
                'grid' => $grid,
                'entrances' => Ferry::normaliseEntrances(
                    $validated['layout']['entrances'] ?? [],
                    count($grid),
                    strlen($grid[0])
                ),
            ],
        ]);

        if ($capacityChanged) {
            $this->reconcileAvailableSeats($ferry);
        }

        return response()->json($this->formatFerry($ferry->refresh(), true));
    }

    public function destroyFerry(Ferry $ferry): JsonResponse
    {
        Gate::authorize('delete', $ferry);

        // Sailings carry tickets, so deleting the boat under them would orphan
        // real bookings. Deactivating is the reversible way to retire one.
        if ($ferry->schedules()->exists()) {
            throw ValidationException::withMessages([
                'ferry' => 'This ferry has sailings on the schedule. Switch it inactive instead - that takes it off sale without touching existing tickets.',
            ]);
        }

        $ferry->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Shared rules. The grid is validated as a whole rather than field by field
     * because "is this a rectangle of legal cells" isn't expressible per-row.
     */
    private function validateFerry(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_per_seat' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'is_active' => ['sometimes', 'boolean'],
            'layout' => ['required', 'array'],
            'layout.grid' => ['required', 'array', 'min:1', 'max:40'],
            'layout.entrances' => ['sometimes', 'array', 'max:12'],
        ]);

        $grid = Ferry::normaliseGrid($validated['layout']['grid'] ?? null);

        if ($grid === null) {
            throw ValidationException::withMessages([
                'layout' => 'The deck plan must be a rectangle, and every cell either a seat or empty space.',
            ]);
        }

        if (strlen($grid[0]) > 20) {
            throw ValidationException::withMessages([
                'layout' => 'A deck can be at most 20 cells wide.',
            ]);
        }

        if (Ferry::seatCount($grid) < 1) {
            throw ValidationException::withMessages([
                'layout' => 'Place at least one seat on the deck.',
            ]);
        }

        return $validated;
    }

    /**
     * Re-derive `available_seats` on sailings that haven't gone yet.
     *
     * That count is stored per sailing and was set from the ferry's capacity at
     * the time the sailing was created. Reshaping a deck without this left every
     * existing sailing quoting the old number - a 40-seat boat cut to 24 showed
     * "39 of 24 seats left" on the booking page.
     *
     * Recomputed from the tickets that actually exist rather than by adjusting
     * the old figure, so a count that had already drifted gets corrected too.
     * Past sailings are left alone: their seat counts are a record of what ran.
     */
    private function reconcileAvailableSeats(Ferry $ferry): void
    {
        $capacity = (int) $ferry->capacity;

        $ferry->schedules()
            ->whereDate('departure_date', '>=', now()->toDateString())
            ->get()
            ->each(function (FerrySchedule $schedule) use ($capacity) {
                $sold = $schedule->tickets()
                    ->whereIn('status', ['pending', 'issued', 'used'])
                    ->count();

                $schedule->update(['available_seats' => max(0, $capacity - $sold)]);
            });
    }

    /** Highest seat number sold on any of this ferry's sailings. */
    private function highestSoldSeat(Ferry $ferry): int
    {
        return (int) FerryTicket::query()
            ->whereIn('schedule_id', $ferry->schedules()->select('id'))
            ->whereIn('status', ['pending', 'issued', 'used'])
            ->max('seat_number');
    }

    private function formatFerry(Ferry $ferry, bool $forManagement = false): array
    {
        $deck = $ferry->deck();

        $payload = [
            'id' => $ferry->id,
            'name' => $ferry->name,
            'capacity' => $deck['capacity'],
            'price_per_seat' => $ferry->price_per_seat,
            'is_active' => $ferry->is_active,
            'rows' => $deck['rows'],
            'columns' => $deck['columns'],
            'layout' => [
                'grid' => $deck['grid'],
                'entrances' => $deck['entrances'],
            ],
        ];

        if ($forManagement) {
            // Lets the editor warn before someone shrinks a deck under a sold
            // seat, rather than only failing on save.
            $payload['has_custom_layout'] = is_array($ferry->layout) && ! empty($ferry->layout['grid']);
            $payload['schedules_count'] = $ferry->schedules_count ?? $ferry->schedules()->count();
            $payload['highest_sold_seat'] = $this->highestSoldSeat($ferry);
        }

        return $payload;
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

        if ($schedule->template_id !== null) {
            $validated['is_overridden'] = true;
        }

        $schedule->update($validated);

        return response()->json($schedule);
    }

    public function destroySchedule(FerrySchedule $schedule): Response
    {
        Gate::authorize('delete', $schedule);

        if ($schedule->template_id !== null) {
            throw ValidationException::withMessages([
                'schedule' => 'This departure was generated by a recurring schedule - cancel it instead of deleting, or the next generation run will recreate it.',
            ]);
        }

        $schedule->delete();

        return response()->noContent();
    }

    public function seats(FerrySchedule $schedule): JsonResponse
    {
        $takenSeats = $schedule->tickets()
            ->whereIn('status', ['pending', 'issued', 'used'])
            ->pluck('seat_number');

        $boardedSeats = $schedule->tickets()
            ->where('status', 'used')
            ->pluck('seat_number');

        $deck = $schedule->ferry->deck();

        return response()->json([
            // From the deck rather than the column: the deck is what the picker
            // draws, so if the two ever disagreed the map would be the thing
            // that's wrong. Save keeps them equal, and the fallback derives one
            // from the other.
            'capacity' => $deck['capacity'],
            'price_per_seat' => $schedule->ferry->price_per_seat,
            'taken_seats' => $takenSeats,
            'boarded_seats' => $boardedSeats,
            // Lets the seat picker draw this boat's actual deck instead of
            // assuming four-plus-aisle-plus-four.
            'layout' => [
                'grid' => $deck['grid'],
                'entrances' => $deck['entrances'],
            ],
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

    /**
     * A ferry_operator selling a walk-up (cash) ticket at the gate on a
     * visitor's behalf - unlike issueTicket, the caller isn't the ticket's
     * owner, so the booking's own user_id is passed through instead of the
     * operator's.
     */
    public function issueWalkupTicket(Request $request): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['ferry_operator', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'schedule_id' => ['required', 'exists:ferry_schedules,id'],
            'booking_id' => ['required', 'exists:bookings,id'],
            'seat_numbers' => ['required', 'array', 'min:1'],
            'seat_numbers.*' => ['integer', 'min:1', 'distinct'],
            'payment_method' => ['required', 'in:online,cash'],
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);
        $tickets = DB::transaction(fn () => $this->tickets->issue($booking->user_id, $validated));

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
        if (! $request->user()->hasAnyRole(['ferry_operator', 'admin'])) {
            abort(403);
        }

        return response()->json($ticket->load(['user', 'schedule.ferry', 'booking']));
    }

    public function validateTicket(Request $request, FerryTicket $ticket): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['ferry_operator', 'admin'])) {
            abort(403);
        }

        if ($ticket->status === 'used') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has already been used.',
            ]);
        }

        if ($ticket->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has been cancelled.',
            ]);
        }

        $ticket->update(['status' => 'used']);

        return response()->json($ticket->load(['user', 'schedule.ferry']));
    }

    public function cancelTicket(Request $request, FerryTicket $ticket): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['ferry_operator', 'admin'])) {
            abort(403);
        }

        if ($ticket->status === 'used') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has already been used.',
            ]);
        }

        if ($ticket->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'This ticket is already cancelled.',
            ]);
        }

        DB::transaction(function () use ($ticket) {
            FerrySchedule::lockForUpdate()->findOrFail($ticket->schedule_id)->increment('available_seats');
            $ticket->update(['status' => 'cancelled']);
        });

        return response()->json($ticket->load(['user', 'schedule.ferry']));
    }

    /**
     * Ties a scanned hotel booking to the whole party's ferry status for one
     * date - a multi-room purchase splits into several booking rows sharing
     * one party (see Booking::partyBookingIds()), so scanning ANY one of
     * them surfaces every ticket already issued to the group for that date,
     * plus how many seats are still unaccounted for (e.g. some of the party
     * paying cash walking up to the gate rather than booking ahead).
     */
    public function partyStatus(Request $request, Booking $booking): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['ferry_operator', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'schedule_id' => ['required', 'exists:ferry_schedules,id'],
        ]);

        $schedule = FerrySchedule::findOrFail($validated['schedule_id']);
        $partyBookingIds = $booking->partyBookingIds();
        $partyGuestsCount = $booking->partyGuestsCount();

        $tickets = FerryTicket::whereIn('booking_id', $partyBookingIds)
            ->whereHas('schedule', fn ($query) => $query->whereDate('departure_date', $schedule->departure_date))
            ->whereIn('status', ['pending', 'issued', 'used'])
            ->with(['user', 'schedule.ferry', 'booking'])
            ->get();

        return response()->json([
            'booking' => $booking->load('room.hotel'),
            'party_guests_count' => $partyGuestsCount,
            'tickets' => $tickets,
            'remaining_seats' => max(0, $partyGuestsCount - $tickets->count()),
        ]);
    }

    public function passengers(Request $request, FerrySchedule $schedule): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['ferry_operator', 'admin'])) {
            abort(403);
        }

        return response()->json(
            $schedule->tickets()->with(['user', 'booking'])->get()
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\HotelBookingService;
use App\Support\AuditLog;
use App\Support\GuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * Legal status transitions. `cancelled` is terminal: cancelling releases the
     * room for anyone else to take, so re-confirming afterwards double-books a
     * bed that has already been sold to a second guest.
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['cancelled'],
        'cancelled' => [],
    ];

    public function __construct(private HotelBookingService $bookings) {}

    public function index(Request $request): JsonResponse
    {
        // Name only. A hotel manager needs to know whose booking this is, not the
        // whole account record - this returned every visitor's email address,
        // verification timestamp and guest flag on every row.
        $query = Booking::query()->with(['room.hotel', 'room.roomType', 'user:id,name']);

        /*
         * Two callers, two meanings. A manager opening the bookings desk wants
         * every booking; the visitor's own "My Trips" wants only theirs - and
         * that page was asking the same URL, so the moment an account gained
         * bookings.manage its trip list filled up with other people's stays.
         * ?mine=1 says "scope to me" regardless of permission.
         */
        $onlyMine = $request->boolean('mine') || ! $request->user()->can('bookings.manage');
        if ($onlyMine) {
            $query->where('user_id', $request->user()->id);
        }

        $bookings = $query->paginate(15);

        // A multi-room purchase splits guests_count across sibling rows, so a
        // single row understates what the party can actually fit (see
        // Booking::partyGuestsCount()). Only computed for a visitor's own,
        // small booking list - not the hotel manager's, which can be large
        // enough that N extra queries per row would matter.
        if ($onlyMine) {
            $bookings->getCollection()->each(
                fn (Booking $booking) => $booking->party_guests_count = $booking->partyGuestsCount()
            );
        }

        return response()->json($bookings);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id && ! $user->can('bookings.manage')) {
            abort(403);
        }

        return response()->json($booking->load('room.hotel', 'room.roomType'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'guests_count' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        // After validation: a malformed booking request must not leave a
        // permanent guest account behind.
        $userId = GuestSession::ensure($request)->id;

        $bookings = DB::transaction(fn () => $this->bookings->create($userId, $validated));

        return response()->json($bookings, 201);
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id && ! $user->can('bookings.manage')) {
            abort(403);
        }

        // Only staff may confirm. `confirmed` is the state that means "paid" -
        // FerryTicketService gates ticket purchase on it - so letting the customer
        // write it made hotel stays and ferry tickets free. Visitors pay through
        // pay() below, which records a payment and owns the transition.
        $isStaff = $user->can('bookings.manage');

        $validated = $request->validate([
            'status' => ['required', $isStaff ? 'in:confirmed,cancelled' : 'in:cancelled'],
        ]);

        $next = $validated['status'];

        if (! in_array($next, self::TRANSITIONS[$booking->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A {$booking->status} booking cannot become {$next}.",
            ]);
        }

        $booking->update([
            'status' => $next,
            ...$next === 'cancelled'
                ? ['cancelled_by' => $user->id, 'cancelled_at' => now()]
                : [],
        ]);

        AuditLog::record("hotel.booking.{$next}", [
            'booking_id' => $booking->id,
            'reference_code' => $booking->reference_code,
            'owner_id' => $booking->user_id,
            'by_staff' => $isStaff,
        ], $request);

        return response()->json($booking);
    }

    /**
     * Settle one or more of the caller's own pending bookings.
     *
     * Takes a group because a multi-room stay is paid for in one action. The
     * amount is read from each booking rather than the request: the SPA computes
     * a total for display only, and trusting it would let a caller name their
     * own price.
     */
    public function pay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1', 'max:20'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
        ]);

        $paid = DB::transaction(function () use ($request, $validated) {
            $bookings = Booking::whereIn('id', $validated['booking_ids'])
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                if ($booking->user_id !== $request->user()->id) {
                    abort(403);
                }

                if ($booking->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'booking_ids' => "Booking {$booking->reference_code} is already {$booking->status} and cannot be paid for.",
                    ]);
                }
            }

            return $this->bookings->settle($bookings, $request->user());
        });

        return response()->json($paid);
    }
}

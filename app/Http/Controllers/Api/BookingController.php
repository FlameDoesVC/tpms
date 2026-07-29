<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\HotelBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(private HotelBookingService $bookings) {}

    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()->with(['room.hotel', 'user']);

        $isOwnBookings = ! $request->user()->hasRole('hotel_manager');
        if ($isOwnBookings) {
            $query->where('user_id', $request->user()->id);
        }

        $bookings = $query->paginate(15);

        // A multi-room purchase splits guests_count across sibling rows, so a
        // single row understates what the party can actually fit (see
        // Booking::partyGuestsCount()). Only computed for a visitor's own,
        // small booking list - not the hotel manager's, which can be large
        // enough that N extra queries per row would matter.
        if ($isOwnBookings) {
            $bookings->getCollection()->each(
                fn (Booking $booking) => $booking->party_guests_count = $booking->partyGuestsCount()
            );
        }

        return response()->json($bookings);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id && ! $user->hasRole('hotel_manager')) {
            abort(403);
        }

        return response()->json($booking->load('room.hotel'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'guests_count' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        $bookings = DB::transaction(fn () => $this->bookings->create($request->user()->id, $validated));

        return response()->json($bookings, 201);
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id && ! $user->hasRole('hotel_manager')) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,cancelled'],
        ]);

        $booking->update($validated);

        return response()->json($booking);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()->with(['room.hotel', 'user']);

        if (! $request->user()->hasRole('hotel_manager')) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->paginate(15));
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
        ]);

        $room = Room::findOrFail($validated['room_id']);

        if ($validated['guests_count'] > $room->max_guests) {
            throw ValidationException::withMessages([
                'guests_count' => 'This room only fits '.$room->max_guests.' guests.',
            ]);
        }

        if (! $room->isAvailableBetween($validated['check_in_date'], $validated['check_out_date'])) {
            throw ValidationException::withMessages([
                'room_id' => 'This room is not available for the selected dates.',
            ]);
        }

        $nights = Carbon::parse($validated['check_in_date'])->diffInDays(Carbon::parse($validated['check_out_date']));

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'room_id' => $room->id,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'guests_count' => $validated['guests_count'],
            'total_price' => $nights * $room->price_per_night,
            'status' => 'pending',
        ]);

        return response()->json($booking, 201);
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

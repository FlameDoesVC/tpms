<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Physical room inventory, for the manager screens. Visitors browse and book
 * room types (see RoomTypeController) and never see a room number until their
 * booking is confirmed.
 */
class RoomController extends Controller
{
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $validated = $request->validate([
            'check_in_date' => ['nullable', 'date', 'required_with:check_out_date'],
            'check_out_date' => ['nullable', 'date', 'after:check_in_date', 'required_with:check_in_date'],
            'room_type_id' => ['nullable', 'integer'],
        ]);

        $rooms = $hotel->rooms()
            ->with('roomType')
            ->when(
                ! empty($validated['room_type_id']),
                fn ($query) => $query->where('room_type_id', $validated['room_type_id'])
            )
            ->get();

        if (! empty($validated['check_in_date'])) {
            $rooms = $rooms->filter(fn (Room $room) => $room->isAvailableBetween(
                $validated['check_in_date'],
                $validated['check_out_date']
            ))->values();
        }

        return RoomResource::collection($rooms)->response();
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        Gate::authorize('update', $hotel);

        $validated = $request->validate([
            'room_number' => ['required', 'string', 'max:50'],
            // Scoped to this hotel: rooms keep their own hotel_id for the
            // bookings relation, and a room whose type belongs elsewhere would
            // price and describe itself from another hotel's inventory.
            'room_type_id' => [
                'required',
                Rule::exists('room_types', 'id')->where('hotel_id', $hotel->id),
            ],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $room = $hotel->rooms()->create($validated)->refresh();

        return (new RoomResource($room->load('roomType')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Room $room): RoomResource
    {
        Gate::authorize('update', $room->hotel);

        $validated = $request->validate([
            'room_number' => ['sometimes', 'string', 'max:50'],
            'room_type_id' => [
                'sometimes',
                Rule::exists('room_types', 'id')->where('hotel_id', $room->hotel_id),
            ],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $room->update($validated);

        return new RoomResource($room->load('roomType'));
    }

    public function destroy(Room $room): Response
    {
        Gate::authorize('update', $room->hotel);

        $room->delete();

        return response()->noContent();
    }
}

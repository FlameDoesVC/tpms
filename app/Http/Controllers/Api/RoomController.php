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

class RoomController extends Controller
{
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $validated = $request->validate([
            'check_in_date' => ['nullable', 'date', 'required_with:check_out_date'],
            'check_out_date' => ['nullable', 'date', 'after:check_in_date', 'required_with:check_in_date'],
        ]);

        $rooms = $hotel->rooms()->get();

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
            'type' => ['required', 'in:single,double,suite'],
            'price_per_night' => ['required', 'numeric', 'min:0'],
            'max_guests' => ['required', 'integer', 'min:1'],
        ]);

        $room = $hotel->rooms()->create($validated)->refresh();

        return (new RoomResource($room))->response()->setStatusCode(201);
    }

    public function update(Request $request, Room $room): RoomResource
    {
        Gate::authorize('update', $room->hotel);

        $validated = $request->validate([
            'room_number' => ['sometimes', 'string', 'max:50'],
            'type' => ['sometimes', 'in:single,double,suite'],
            'price_per_night' => ['sometimes', 'numeric', 'min:0'],
            'max_guests' => ['sometimes', 'integer', 'min:1'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $room->update($validated);

        return new RoomResource($room);
    }

    public function destroy(Room $room): Response
    {
        Gate::authorize('update', $room->hotel);

        $room->delete();

        return response()->noContent();
    }
}

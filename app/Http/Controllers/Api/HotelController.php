<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class HotelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Capped rather than unbounded so a hostile per_page can't ask for the
        // whole table; the visitor browse page asks for 100 to get every hotel
        // in one pass, since its ?hotel=<id> deep links scroll to a section
        // that has to already be on the page.
        $perPage = min((int) $request->integer('per_page', 12) ?: 12, 100);

        // `?all=1` is the management view and is ignored for anyone without a
        // staff role - a deactivated hotel is not something a visitor should see.
        $hotels = Hotel::query()
            ->visibleTo($request->user(), $request->boolean('all'))
            ->with('media')
            ->paginate($perPage);

        return HotelResource::collection($hotels)->response();
    }

    /**
     * Top hotels by non-cancelled booking count, for the homepage.
     */
    public function popular(): JsonResponse
    {
        $hotels = Hotel::query()
            ->where('is_active', true)
            ->with('media')
            ->withCount(['bookings' => fn ($query) => $query->where('status', '!=', 'cancelled')])
            ->orderByDesc('bookings_count')
            ->limit(3)
            ->get();

        return response()->json($hotels);
    }

    public function show(Request $request, Hotel $hotel): HotelResource
    {
        // 404 rather than 403: whether a hidden hotel exists is itself the thing
        // being withheld.
        abort_unless($hotel->visibleTo($request->user()), 404);

        return new HotelResource($hotel->load('rooms'));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Hotel::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address' => ['required', 'string', 'max:255'],
            'total_rooms' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        $hotel = Hotel::create(collect($validated)->except('image')->all())->refresh();

        if ($request->hasFile('image')) {
            $hotel->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return (new HotelResource($hotel))->response()->setStatusCode(201);
    }

    public function update(Request $request, Hotel $hotel): HotelResource
    {
        Gate::authorize('update', $hotel);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address' => ['sometimes', 'string', 'max:255'],
            'total_rooms' => ['sometimes', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hotel->update(collect($validated)->except(['image', 'remove_image'])->all());

        if ($request->hasFile('image')) {
            $hotel->addMediaFromRequest('image')->toMediaCollection('image');
        } elseif ($request->boolean('remove_image')) {
            $hotel->clearMediaCollection('image');
        }

        return new HotelResource($hotel);
    }

    public function destroy(Hotel $hotel): Response
    {
        Gate::authorize('delete', $hotel);

        $hotel->delete();

        return response()->noContent();
    }
}

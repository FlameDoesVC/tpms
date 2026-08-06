<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ManagesGallery;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoomTypeResource;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Support\FacilityCatalog;
use App\Support\RoomTypeAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomTypeController extends Controller
{
    use ManagesGallery;

    /**
     * The room types a hotel offers, with how many rooms of each are free for
     * the requested stay. This is what the hotel detail page books from.
     */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($hotel->visibleTo($request->user()), 404);

        $validated = $request->validate([
            'check_in_date' => ['nullable', 'date', 'required_with:check_out_date'],
            'check_out_date' => ['nullable', 'date', 'after:check_in_date', 'required_with:check_in_date'],
        ]);

        // `?all=1` is the manager view, which needs deactivated types to be able
        // to reactivate them. Ignored for anyone without the role.
        $wantsAll = $request->boolean('all')
            && $request->user()?->hasAnyRole(['hotel_manager', 'admin']);

        $roomTypes = $hotel->roomTypes()
            ->when(! $wantsAll, fn ($query) => $query->where('is_active', true))
            ->with('media')
            ->withCount('rooms')
            ->orderBy('price_per_night')
            ->get();

        RoomTypeAvailability::attach($roomTypes, RoomTypeAvailability::forHotel(
            $hotel,
            $validated['check_in_date'] ?? null,
            $validated['check_out_date'] ?? null,
        ));

        return RoomTypeResource::collection($roomTypes)->response();
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        Gate::authorize('update', $hotel);

        $validated = $request->validate($this->rules($hotel, creating: true));

        $roomType = $hotel->roomTypes()->create(
            collect($validated)->except(['image', 'remove_image'])->all()
        )->refresh();

        if ($request->hasFile('image')) {
            $roomType->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return (new RoomTypeResource($roomType))->response()->setStatusCode(201);
    }

    public function update(Request $request, RoomType $roomType): RoomTypeResource
    {
        Gate::authorize('update', $roomType->hotel);

        $validated = $request->validate($this->rules($roomType->hotel, creating: false, ignoring: $roomType));

        $roomType->update(collect($validated)->except(['image', 'remove_image'])->all());

        if ($request->hasFile('image')) {
            $roomType->addMediaFromRequest('image')->toMediaCollection('image');
        } elseif ($request->boolean('remove_image')) {
            $roomType->clearMediaCollection('image');
        }

        return new RoomTypeResource($roomType->refresh());
    }

    public function destroy(RoomType $roomType): Response
    {
        Gate::authorize('update', $roomType->hotel);

        // Deleting would cascade the rooms away, and their bookings with them.
        // Reassigning or removing the inventory first has to be a deliberate act.
        if ($roomType->rooms()->exists()) {
            throw ValidationException::withMessages([
                'room_type' => 'This room type still has rooms assigned - move or delete them first.',
            ]);
        }

        $roomType->delete();

        return response()->noContent();
    }

    public function storeGallery(Request $request, RoomType $roomType): JsonResponse
    {
        Gate::authorize('update', $roomType->hotel);

        return $this->addGalleryImages($request, $roomType);
    }

    public function destroyGalleryImage(RoomType $roomType, int $media): JsonResponse
    {
        Gate::authorize('update', $roomType->hotel);

        return $this->removeGalleryImage($roomType, $media);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Hotel $hotel, bool $creating, ?RoomType $ignoring = null): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        $unique = Rule::unique('room_types', 'name')->where('hotel_id', $hotel->id);
        if ($ignoring !== null) {
            $unique->ignore($ignoring->id);
        }

        return [
            'name' => [$presence, 'string', 'max:255', $unique],
            'description' => ['nullable', 'string'],
            'price_per_night' => [$presence, 'numeric', 'min:0'],
            'max_guests' => [$presence, 'integer', 'min:1'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', Rule::in(FacilityCatalog::roomAmenities())],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }
}

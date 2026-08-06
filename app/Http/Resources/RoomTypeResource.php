<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'name' => $this->name,
            'description' => $this->description,
            'price_per_night' => $this->price_per_night,
            'max_guests' => $this->max_guests,
            'amenities' => $this->amenities ?? [],
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'gallery' => $this->galleryItems(),
            // Set by the controller when a stay was supplied; absent rather than
            // zero when it wasn't, so the UI can tell "sold out" from "unknown".
            'available_count' => $this->when(isset($this->available_count), fn () => $this->available_count),
            'rooms_count' => $this->whenCounted('rooms'),
        ];
    }
}

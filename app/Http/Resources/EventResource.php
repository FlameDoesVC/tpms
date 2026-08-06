<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Replaces the raw ThemeParkEvent models these endpoints used to return, which
 * exposed every new column automatically. Response shape is unchanged -
 * AppServiceProvider calls JsonResource::withoutWrapping().
 */
class EventResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'highlights' => $this->highlights ?? [],
            'type' => $this->type,
            'location' => $this->location,
            'duration_minutes' => $this->duration_minutes,
            'min_age' => $this->min_age,
            'min_height_cm' => $this->min_height_cm,
            'capacity_per_slot' => $this->capacity_per_slot,
            'price_per_ticket' => $this->price_per_ticket,
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'gallery' => $this->galleryItems(),
            'slots' => $this->whenLoaded('slots'),
            'bookings_count' => $this->whenCounted('bookings'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

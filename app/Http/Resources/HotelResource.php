<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelResource extends JsonResource
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
            'address' => $this->address,
            'facilities' => $this->facilities ?? [],
            // Normalised to H:i - a TIME column reads back as '14:00:00' on
            // MySQL but as whatever was written on SQLite, and the UI renders
            // the value directly.
            'check_in_time' => $this->formatTime($this->check_in_time),
            'check_out_time' => $this->formatTime($this->check_out_time),
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'total_rooms' => $this->total_rooms,
            'image_url' => $this->image_url,
            'gallery' => $this->galleryItems(),
            'is_active' => $this->is_active,
            'room_types' => RoomTypeResource::collection($this->whenLoaded('roomTypes')),
            // Physical inventory, for the manager screens only.
            'rooms' => RoomResource::collection($this->whenLoaded('rooms')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function formatTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}

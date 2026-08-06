<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            // Closure so the type lands in the same hotel as the room, whether
            // that hotel came from the factory above or was passed in.
            'room_type_id' => fn (array $attributes) => RoomType::factory()->create([
                'hotel_id' => $attributes['hotel_id'],
            ])->id,
            'room_number' => fake()->unique()->numerify('###'),
            'is_available' => true,
        ];
    }

    /**
     * Sibling rooms of one type - the common shape in tests, where a party needs
     * several rooms of the same kind.
     */
    public function forType(RoomType $roomType): static
    {
        return $this->state(fn () => [
            'hotel_id' => $roomType->hotel_id,
            'room_type_id' => $roomType->id,
        ]);
    }
}

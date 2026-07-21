<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Room;
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
            'room_number' => fake()->unique()->numerify('###'),
            'type' => fake()->randomElement(['single', 'double', 'suite']),
            'price_per_night' => fake()->randomFloat(2, 50, 500),
            'max_guests' => fake()->numberBetween(1, 6),
            'is_available' => true,
        ];
    }
}

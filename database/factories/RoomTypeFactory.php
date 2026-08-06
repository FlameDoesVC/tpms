<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\RoomType;
use App\Support\FacilityCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
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
            // Unique because (hotel_id, name) is, and tests routinely give one
            // hotel several types.
            'name' => fake()->unique()->words(2, true).' Room',
            'description' => fake()->paragraph(),
            'price_per_night' => fake()->randomFloat(2, 50, 500),
            'max_guests' => fake()->numberBetween(1, 6),
            'amenities' => fake()->randomElements(FacilityCatalog::roomAmenities(), 3),
            'is_active' => true,
        ];
    }
}

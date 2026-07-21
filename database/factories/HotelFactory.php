<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Resort',
            'description' => fake()->paragraph(),
            'address' => fake()->address(),
            'total_rooms' => fake()->numberBetween(5, 50),
            'image_url' => null,
            'is_active' => true,
        ];
    }
}

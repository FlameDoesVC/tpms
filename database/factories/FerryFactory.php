<?php

namespace Database\Factories;

use App\Models\Ferry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ferry>
 */
class FerryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Ferry',
            'capacity' => fake()->numberBetween(20, 100),
            'is_active' => true,
        ];
    }
}

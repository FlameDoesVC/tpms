<?php

namespace Database\Factories;

use App\Models\ThemeParkEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThemeParkEvent>
 */
class ThemeParkEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'highlights' => fake()->sentences(3),
            'type' => fake()->randomElement(['ride', 'show', 'beach_event']),
            'location' => fake()->streetName(),
            'duration_minutes' => fake()->numberBetween(15, 90),
            'min_age' => null,
            'min_height_cm' => null,
            'capacity_per_slot' => fake()->numberBetween(10, 50),
            'price_per_ticket' => fake()->randomFloat(2, 5, 50),
            'is_active' => true,
        ];
    }
}

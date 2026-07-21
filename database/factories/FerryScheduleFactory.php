<?php

namespace Database\Factories;

use App\Models\Ferry;
use App\Models\FerrySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FerrySchedule>
 */
class FerryScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ferry_id' => Ferry::factory(),
            'departure_date' => fake()->dateTimeBetween('+1 day', '+10 days')->format('Y-m-d'),
            'departure_time' => '09:00:00',
            'arrival_time' => '10:30:00',
            'available_seats' => 40,
            'status' => 'scheduled',
        ];
    }
}

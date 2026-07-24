<?php

namespace Database\Factories;

use App\Models\Ferry;
use App\Models\FerryScheduleTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FerryScheduleTemplate>
 */
class FerryScheduleTemplateFactory extends Factory
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
            'frequency' => 'daily',
            'weekdays' => null,
            'day_of_month' => null,
            'departure_time' => '09:00:00',
            'arrival_time' => '10:30:00',
            'available_seats' => 40,
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
            'is_active' => true,
        ];
    }

    public function weekly(array $weekdays): static
    {
        return $this->state(fn () => [
            'frequency' => 'weekly',
            'weekdays' => $weekdays,
        ]);
    }

    public function monthly(int $dayOfMonth): static
    {
        return $this->state(fn () => [
            'frequency' => 'monthly',
            'day_of_month' => $dayOfMonth,
        ]);
    }
}

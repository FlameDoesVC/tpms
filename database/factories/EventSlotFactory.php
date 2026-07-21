<?php

namespace Database\Factories;

use App\Models\EventSlot;
use App\Models\ThemeParkEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSlot>
 */
class EventSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => ThemeParkEvent::factory(),
            'slot_date' => fake()->dateTimeBetween('+1 day', '+10 days')->format('Y-m-d'),
            'slot_time' => '10:00:00',
            'available_capacity' => 20,
            'status' => 'scheduled',
        ];
    }
}

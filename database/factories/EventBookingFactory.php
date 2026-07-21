<?php

namespace Database\Factories;

use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventBooking>
 */
class EventBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_slot_id' => EventSlot::factory(),
            'ticket_count' => fake()->numberBetween(1, 4),
            'status' => 'confirmed',
        ];
    }
}

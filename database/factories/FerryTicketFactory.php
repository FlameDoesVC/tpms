<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FerryTicket>
 */
class FerryTicketFactory extends Factory
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
            'schedule_id' => FerrySchedule::factory(),
            'booking_id' => Booking::factory(['status' => 'confirmed']),
            'seat_number' => fake()->numberBetween(1, 40),
            'status' => 'issued',
        ];
    }
}

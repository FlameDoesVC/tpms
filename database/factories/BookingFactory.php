<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 day', '+10 days');
        $checkOut = (clone $checkIn)->modify('+'.fake()->numberBetween(1, 5).' days');

        return [
            'user_id' => User::factory(),
            'room_id' => Room::factory(),
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'total_price' => fake()->randomFloat(2, 50, 2000),
            'status' => 'pending',
            'guests_count' => fake()->numberBetween(1, 4),
        ];
    }
}

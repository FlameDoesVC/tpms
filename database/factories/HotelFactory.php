<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Support\FacilityCatalog;
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
            'facilities' => fake()->randomElements(FacilityCatalog::hotelFacilities(), 5),
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'website' => fake()->url(),
            'total_rooms' => fake()->numberBetween(5, 50),
            'is_active' => true,
        ];
    }
}

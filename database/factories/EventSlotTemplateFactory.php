<?php

namespace Database\Factories;

use App\Models\EventSlotTemplate;
use App\Models\ThemeParkEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSlotTemplate>
 */
class EventSlotTemplateFactory extends Factory
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
            'frequency' => 'daily',
            'weekdays' => null,
            'day_of_month' => null,
            'slot_time' => '10:00:00',
            'available_capacity' => 20,
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

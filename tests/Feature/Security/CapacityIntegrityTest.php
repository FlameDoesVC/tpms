<?php

namespace Tests\Feature\Security;

use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding F-04: theme-park cancellation was not idempotent, so repeated
 * DELETEs each returned the seats again. Five identical calls took a slot to 18
 * available seats against a physical capacity of 10.
 */
class CapacityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_cancellation_does_not_inflate_capacity(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $event = ThemeParkEvent::factory()->create(['capacity_per_slot' => 10]);
        $slot = EventSlot::factory()->create([
            'event_id' => $event->id,
            'available_capacity' => 8,
            'status' => 'scheduled',
        ]);
        $booking = EventBooking::factory()->create([
            'user_id' => $visitor->id,
            'event_slot_id' => $slot->id,
            'ticket_count' => 2,
            'status' => 'confirmed',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($visitor)->deleteJson("/api/themepark/bookings/{$booking->id}");
        }

        $this->assertSame(10, $slot->fresh()->available_capacity,
            'F-04: repeated cancellation inflated the slot past its capacity.');
    }

    public function test_capacity_never_exceeds_the_events_limit(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $event = ThemeParkEvent::factory()->create(['capacity_per_slot' => 10]);
        $slot = EventSlot::factory()->create([
            'event_id' => $event->id,
            // Already at capacity: a restore here must not push it over.
            'available_capacity' => 10,
            'status' => 'scheduled',
        ]);
        $booking = EventBooking::factory()->create([
            'user_id' => $visitor->id,
            'event_slot_id' => $slot->id,
            'ticket_count' => 3,
            'status' => 'confirmed',
        ]);

        $this->actingAs($visitor)->deleteJson("/api/themepark/bookings/{$booking->id}");

        $this->assertLessThanOrEqual(10, $slot->fresh()->available_capacity,
            'F-04: available capacity exceeded capacity_per_slot.');
    }

    public function test_a_used_ticket_cannot_be_cancelled(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $slot = EventSlot::factory()->create(['available_capacity' => 5, 'status' => 'scheduled']);
        $booking = EventBooking::factory()->create([
            'user_id' => $visitor->id,
            'event_slot_id' => $slot->id,
            'ticket_count' => 2,
            'status' => 'used',
        ]);

        $this->actingAs($visitor)
            ->deleteJson("/api/themepark/bookings/{$booking->id}")
            ->assertUnprocessable();

        $this->assertSame(5, $slot->fresh()->available_capacity);
    }

    /** The first, legitimate cancellation must still return the seats. */
    public function test_first_cancellation_still_returns_the_seats(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $event = ThemeParkEvent::factory()->create(['capacity_per_slot' => 20]);
        $slot = EventSlot::factory()->create([
            'event_id' => $event->id,
            'available_capacity' => 8,
            'status' => 'scheduled',
        ]);
        $booking = EventBooking::factory()->create([
            'user_id' => $visitor->id,
            'event_slot_id' => $slot->id,
            'ticket_count' => 2,
            'status' => 'confirmed',
        ]);

        $this->actingAs($visitor)
            ->deleteJson("/api/themepark/bookings/{$booking->id}")
            ->assertOk();

        $this->assertSame(10, $slot->fresh()->available_capacity);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}

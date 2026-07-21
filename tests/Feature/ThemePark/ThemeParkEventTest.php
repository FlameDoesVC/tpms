<?php

namespace Tests\Feature\ThemePark;

use App\Models\EventSlot;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeParkEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_events(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        ThemeParkEvent::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/themepark/events');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_show_returns_event_with_slots_for_a_date(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $event = ThemeParkEvent::factory()->create();
        EventSlot::factory()->create(['event_id' => $event->id, 'slot_date' => '2026-08-10']);
        EventSlot::factory()->create(['event_id' => $event->id, 'slot_date' => '2026-08-11']);

        $response = $this->actingAs($user)->getJson("/api/themepark/events/{$event->id}?date=2026-08-10");

        $response->assertOk()->assertJsonCount(1, 'slots');
    }

    public function test_staff_can_create_event(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');

        $response = $this->actingAs($staff)->postJson('/api/themepark/events', [
            'name' => 'Wave Pool',
            'type' => 'beach_event',
            'location' => 'North Shore',
            'duration_minutes' => 30,
            'capacity_per_slot' => 20,
            'price_per_ticket' => 10,
        ]);

        $response->assertCreated()->assertJsonPath('name', 'Wave Pool');
    }

    public function test_visitor_cannot_create_event(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $this->actingAs($visitor)->postJson('/api/themepark/events', [
            'name' => 'Wave Pool',
            'type' => 'beach_event',
            'location' => 'North Shore',
            'duration_minutes' => 30,
            'capacity_per_slot' => 20,
            'price_per_ticket' => 10,
        ])->assertForbidden();
    }

    public function test_staff_can_toggle_event_active(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create(['is_active' => true]);

        $response = $this->actingAs($staff)->patchJson("/api/themepark/events/{$event->id}", [
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('is_active', false);
    }

    public function test_staff_can_delete_event(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();

        $this->actingAs($staff)->deleteJson("/api/themepark/events/{$event->id}")
            ->assertNoContent();
    }

    public function test_staff_can_add_a_slot_to_an_event(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create(['capacity_per_slot' => 25]);

        $response = $this->actingAs($staff)->postJson("/api/themepark/events/{$event->id}/slots", [
            'slot_date' => '2026-09-01',
            'slot_time' => '14:00',
        ]);

        $response->assertCreated()->assertJsonPath('available_capacity', 25);
    }

    public function test_visitor_can_book_a_slot(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $slot = EventSlot::factory()->create(['available_capacity' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/themepark/bookings', [
            'event_slot_id' => $slot->id,
            'ticket_count' => 3,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'confirmed');
        $this->assertEquals(7, $slot->fresh()->available_capacity);
    }

    public function test_booking_fails_when_capacity_exceeded(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $slot = EventSlot::factory()->create(['available_capacity' => 2]);

        $this->actingAs($visitor)->postJson('/api/themepark/bookings', [
            'event_slot_id' => $slot->id,
            'ticket_count' => 3,
        ])->assertUnprocessable();
    }

    public function test_visitor_sees_only_their_own_bookings(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        \App\Models\EventBooking::factory()->create(['user_id' => $visitor->id]);
        \App\Models\EventBooking::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($visitor)->getJson('/api/themepark/bookings');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_visitor_can_cancel_their_booking_and_capacity_is_restored(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $slot = EventSlot::factory()->create(['available_capacity' => 10]);
        $booking = \App\Models\EventBooking::factory()->create([
            'user_id' => $visitor->id,
            'event_slot_id' => $slot->id,
            'ticket_count' => 2,
            'status' => 'confirmed',
        ]);

        $this->actingAs($visitor)->deleteJson("/api/themepark/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertEquals(12, $slot->fresh()->available_capacity);
    }

    public function test_user_cannot_cancel_someone_elses_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        $booking = \App\Models\EventBooking::factory()->create(['user_id' => $other->id]);

        $this->actingAs($visitor)->deleteJson("/api/themepark/bookings/{$booking->id}")
            ->assertForbidden();
    }
}

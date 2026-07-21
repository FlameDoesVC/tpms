<?php

namespace Tests\Feature\ThemePark;

use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeParkTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_sell_a_walkin_ticket(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $slot = EventSlot::factory()->create(['available_capacity' => 10]);

        $response = $this->actingAs($staff)->postJson('/api/themepark/tickets/sell', [
            'event_slot_id' => $slot->id,
            'ticket_count' => 2,
            'visitor_name' => 'Walk-in Guest',
        ]);

        $response->assertCreated()
            ->assertJsonPath('visitor_name', 'Walk-in Guest')
            ->assertJsonPath('status', 'confirmed');
        $this->assertEquals(8, $slot->fresh()->available_capacity);
    }

    public function test_visitor_cannot_sell_a_ticket(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $slot = EventSlot::factory()->create();

        $this->actingAs($visitor)->postJson('/api/themepark/tickets/sell', [
            'event_slot_id' => $slot->id,
            'ticket_count' => 1,
        ])->assertForbidden();
    }

    public function test_staff_can_validate_a_ticket(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $booking = EventBooking::factory()->create(['status' => 'confirmed']);

        $response = $this->actingAs($staff)->postJson("/api/themepark/tickets/{$booking->id}/validate");

        $response->assertOk()->assertJsonPath('status', 'used');
    }

    public function test_validating_an_already_used_ticket_fails(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $booking = EventBooking::factory()->create(['status' => 'used']);

        $this->actingAs($staff)->postJson("/api/themepark/tickets/{$booking->id}/validate")
            ->assertUnprocessable();
    }

    public function test_staff_can_get_daily_sales_report(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create(['price_per_ticket' => 10]);
        $slot = EventSlot::factory()->create(['event_id' => $event->id, 'slot_date' => '2026-08-20']);
        EventBooking::factory()->create(['event_slot_id' => $slot->id, 'ticket_count' => 3, 'status' => 'confirmed']);
        EventBooking::factory()->create(['event_slot_id' => $slot->id, 'ticket_count' => 2, 'status' => 'cancelled']);

        $response = $this->actingAs($staff)->getJson('/api/themepark/reports/sales?date=2026-08-20');

        $response->assertOk();
        $report = collect($response->json())->firstWhere('event_id', $event->id);
        $this->assertEquals(3, $report['tickets_sold']);
        $this->assertEquals('30.00', $report['revenue']);
    }

    public function test_visitor_cannot_get_sales_report(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $this->actingAs($visitor)->getJson('/api/themepark/reports/sales?date=2026-08-20')
            ->assertForbidden();
    }

    public function test_staff_can_get_capacity_status(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create(['capacity_per_slot' => 20]);
        EventSlot::factory()->create([
            'event_id' => $event->id,
            'slot_date' => now()->toDateString(),
            'available_capacity' => 5,
        ]);

        $response = $this->actingAs($staff)->getJson('/api/themepark/capacity');

        $response->assertOk();
        $this->assertEquals(75, $response->json('0.fill_percentage'));
    }
}

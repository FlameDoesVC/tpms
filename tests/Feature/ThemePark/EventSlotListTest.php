<?php

namespace Tests\Feature\ThemePark;

use App\Models\EventSlot;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSlotListTest extends TestCase
{
    use RefreshDatabase;

    public function test_slots_can_be_listed_across_all_events(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $eventA = ThemeParkEvent::factory()->create();
        $eventB = ThemeParkEvent::factory()->create();
        EventSlot::factory()->create(['event_id' => $eventA->id, 'slot_date' => '2026-08-10']);
        EventSlot::factory()->create(['event_id' => $eventB->id, 'slot_date' => '2026-08-11']);

        $response = $this->actingAs($user)->getJson('/api/themepark/slots');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_slots_can_be_filtered_by_date(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $event = ThemeParkEvent::factory()->create();
        EventSlot::factory()->create(['event_id' => $event->id, 'slot_date' => '2026-08-10']);
        EventSlot::factory()->create(['event_id' => $event->id, 'slot_date' => '2026-08-11']);

        $response = $this->actingAs($user)->getJson('/api/themepark/slots?date=2026-08-10');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }
}

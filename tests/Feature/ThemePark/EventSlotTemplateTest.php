<?php

namespace Tests\Feature\ThemePark;

use App\Models\EventSlot;
use App\Models\EventSlotTemplate;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSlotTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_can_list_templates(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();
        EventSlotTemplate::factory()->count(2)->create(['event_id' => $event->id]);

        $response = $this->actingAs($staff)->getJson('/api/themepark/slot-templates');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_visitor_cannot_list_templates(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $this->actingAs($visitor)->getJson('/api/themepark/slot-templates')->assertForbidden();
    }

    public function test_staff_can_create_daily_template_and_instances_generate_immediately(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();

        $response = $this->actingAs($staff)->postJson('/api/themepark/slot-templates', [
            'event_id' => $event->id,
            'frequency' => 'daily',
            'slot_time' => '10:00',
            'available_capacity' => 20,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $response->assertCreated();
        $templateId = $response->json('id');
        $this->assertSame(5, EventSlot::where('template_id', $templateId)->count());
    }

    public function test_creating_a_daily_template_tolerates_blank_weekdays_and_day_of_month(): void
    {
        // The frontend always sends `weekdays` and `day_of_month` in the
        // payload (as [] / '') regardless of which frequency is selected,
        // since only one of the two is ever relevant at a time.
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();

        $response = $this->actingAs($staff)->postJson('/api/themepark/slot-templates', [
            'event_id' => $event->id,
            'frequency' => 'daily',
            'weekdays' => [],
            'day_of_month' => '',
            'slot_time' => '10:00',
            'available_capacity' => 20,
            'starts_on' => '2026-08-01',
            'ends_on' => '',
        ]);

        $response->assertCreated();
    }

    public function test_creating_weekly_template_requires_weekdays(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();

        $response = $this->actingAs($staff)->postJson('/api/themepark/slot-templates', [
            'event_id' => $event->id,
            'frequency' => 'weekly',
            'slot_time' => '10:00',
            'available_capacity' => 20,
            'starts_on' => '2026-08-01',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['weekdays']);
    }

    public function test_visitor_cannot_create_template(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $event = ThemeParkEvent::factory()->create();

        $this->actingAs($visitor)->postJson('/api/themepark/slot-templates', [
            'event_id' => $event->id,
            'frequency' => 'daily',
            'slot_time' => '10:00',
            'available_capacity' => 20,
            'starts_on' => '2026-08-01',
        ])->assertForbidden();
    }

    public function test_staff_can_update_template_and_reconciliation_runs_immediately(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $template = EventSlotTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'slot_time' => '10:00:00',
        ]);
        $instance = EventSlot::factory()->create([
            'event_id' => $template->event_id,
            'template_id' => $template->id,
            'slot_date' => '2026-08-10',
            'slot_time' => '10:00:00',
        ]);

        $response = $this->actingAs($staff)->patchJson("/api/themepark/slot-templates/{$template->id}", [
            'slot_time' => '12:00',
        ]);

        $response->assertOk()->assertJsonPath('slot_time', '12:00');
        $this->assertSame('12:00', $instance->fresh()->slot_time);
    }

    public function test_staff_can_stop_a_template_without_deleting_instances(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $template = EventSlotTemplate::factory()->create(['frequency' => 'daily', 'starts_on' => '2026-08-01']);
        $instance = EventSlot::factory()->create([
            'event_id' => $template->event_id,
            'template_id' => $template->id,
            'slot_date' => '2026-08-10',
        ]);

        $this->actingAs($staff)->deleteJson("/api/themepark/slot-templates/{$template->id}")
            ->assertNoContent();

        $this->assertFalse($template->fresh()->is_active);
        $this->assertDatabaseHas('event_slots', ['id' => $instance->id]);
    }

    public function test_visitor_cannot_update_or_stop_a_template(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $template = EventSlotTemplate::factory()->create();

        $this->actingAs($visitor)->patchJson("/api/themepark/slot-templates/{$template->id}", [
            'slot_time' => '12:00',
        ])->assertForbidden();

        $this->actingAs($visitor)->deleteJson("/api/themepark/slot-templates/{$template->id}")
            ->assertForbidden();
    }

    public function test_updating_a_template_generated_slot_marks_it_overridden(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $template = EventSlotTemplate::factory()->create();
        $slot = EventSlot::factory()->create(['event_id' => $template->event_id, 'template_id' => $template->id]);

        $response = $this->actingAs($staff)->patchJson("/api/themepark/slots/{$slot->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('is_overridden', true);
    }

    public function test_updating_a_manual_slot_does_not_mark_it_overridden(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $slot = EventSlot::factory()->create();

        $response = $this->actingAs($staff)->patchJson("/api/themepark/slots/{$slot->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertOk()->assertJsonPath('is_overridden', false);
    }

    public function test_deleting_a_template_generated_slot_is_rejected(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $template = EventSlotTemplate::factory()->create();
        $slot = EventSlot::factory()->create(['event_id' => $template->event_id, 'template_id' => $template->id]);

        $this->actingAs($staff)->deleteJson("/api/themepark/slots/{$slot->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('event_slots', ['id' => $slot->id]);
    }

    public function test_deleting_a_manual_slot_still_works(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $slot = EventSlot::factory()->create();

        $this->actingAs($staff)->deleteJson("/api/themepark/slots/{$slot->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('event_slots', ['id' => $slot->id]);
    }
}

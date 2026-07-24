<?php

namespace Tests\Feature\ThemePark;

use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\EventSlotTemplate;
use App\Models\ThemeParkEvent;
use App\Services\EventSlotTemplateGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSlotTemplateGeneratorTest extends TestCase
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

    public function test_generate_creates_daily_instances_within_the_rolling_window(): void
    {
        $event = ThemeParkEvent::factory()->create();
        $template = EventSlotTemplate::factory()->create([
            'event_id' => $event->id,
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'ends_on' => null,
            'slot_time' => '10:00:00',
            'available_capacity' => 15,
        ]);

        (new EventSlotTemplateGenerator)->generate();

        $this->assertSame(61, EventSlot::where('template_id', $template->id)->count());
        $first = EventSlot::where('template_id', $template->id)->orderBy('slot_date')->first();
        $this->assertSame('2026-08-01', $first->slot_date->toDateString());
        $this->assertSame('10:00:00', $first->slot_time);
        $this->assertSame(15, $first->available_capacity);
        $this->assertSame('scheduled', $first->status);
    }

    public function test_generate_does_not_duplicate_instances_on_rerun(): void
    {
        $template = EventSlotTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
        ]);

        $generator = new EventSlotTemplateGenerator;
        $generator->generate();
        $generator->generate();

        $this->assertSame(61, EventSlot::where('template_id', $template->id)->count());
    }

    public function test_generate_only_creates_matching_weekdays(): void
    {
        $template = EventSlotTemplate::factory()->weekly([1])->create([
            'starts_on' => '2026-08-01', // Saturday
            'ends_on' => '2026-08-14',
        ]);

        (new EventSlotTemplateGenerator)->generate();

        $dates = EventSlot::where('template_id', $template->id)->pluck('slot_date')
            ->map(fn ($d) => $d->toDateString())->all();
        $this->assertEqualsCanonicalizing(['2026-08-03', '2026-08-10'], $dates);
    }

    public function test_generate_skips_inactive_templates(): void
    {
        $template = EventSlotTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'is_active' => false,
        ]);

        (new EventSlotTemplateGenerator)->generate();

        $this->assertSame(0, EventSlot::where('template_id', $template->id)->count());
    }

    public function test_reconcile_syncs_untouched_future_instance_fields_from_template(): void
    {
        $template = EventSlotTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'slot_time' => '10:00:00',
            'available_capacity' => 15,
        ]);
        $instance = EventSlot::factory()->create([
            'event_id' => $template->event_id,
            'template_id' => $template->id,
            'slot_date' => '2026-08-10',
            'slot_time' => '08:00:00',
            'available_capacity' => 15,
        ]);

        $template->update(['slot_time' => '12:00:00', 'available_capacity' => 30]);
        (new EventSlotTemplateGenerator)->reconcile();

        $instance->refresh();
        $this->assertSame('12:00:00', $instance->slot_time);
        $this->assertSame(30, $instance->available_capacity);
        $this->assertFalse($instance->is_overridden);
    }

    public function test_reconcile_leaves_instance_with_active_bookings_untouched(): void
    {
        $template = EventSlotTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'slot_time' => '10:00:00',
        ]);
        $instance = EventSlot::factory()->create([
            'event_id' => $template->event_id,
            'template_id' => $template->id,
            'slot_date' => '2026-08-10',
            'slot_time' => '08:00:00',
        ]);
        EventBooking::factory()->create(['event_slot_id' => $instance->id, 'status' => 'confirmed']);

        $template->update(['slot_time' => '12:00:00']);
        (new EventSlotTemplateGenerator)->reconcile();

        $instance->refresh();
        $this->assertSame('08:00:00', $instance->slot_time);
        $this->assertFalse($instance->is_overridden);
    }

    public function test_reconcile_flags_instance_whose_date_no_longer_matches_the_rule(): void
    {
        $template = EventSlotTemplate::factory()->weekly([1, 5])->create([
            'starts_on' => '2026-08-01',
        ]);
        $instance = EventSlot::factory()->create([
            'event_id' => $template->event_id,
            'template_id' => $template->id,
            'slot_date' => '2026-08-07', // Friday, matched the original rule
        ]);

        $template->update(['weekdays' => [1]]);
        (new EventSlotTemplateGenerator)->reconcile();

        $instance->refresh();
        $this->assertTrue($instance->is_overridden);
        $this->assertDatabaseHas('event_slots', ['id' => $instance->id, 'status' => 'scheduled']);
    }

    public function test_reconcile_skips_already_overridden_instances(): void
    {
        $template = EventSlotTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'slot_time' => '10:00:00',
        ]);
        $instance = EventSlot::factory()->create([
            'event_id' => $template->event_id,
            'template_id' => $template->id,
            'slot_date' => '2026-08-10',
            'slot_time' => '08:00:00',
            'is_overridden' => true,
        ]);

        $template->update(['slot_time' => '12:00:00']);
        (new EventSlotTemplateGenerator)->reconcile();

        $instance->refresh();
        $this->assertSame('08:00:00', $instance->slot_time);
    }
}

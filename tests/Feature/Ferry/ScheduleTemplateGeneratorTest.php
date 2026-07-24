<?php

namespace Tests\Feature\Ferry;

use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryScheduleTemplate;
use App\Models\FerryTicket;
use App\Services\ScheduleTemplateGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTemplateGeneratorTest extends TestCase
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
        $ferry = Ferry::factory()->create();
        $template = FerryScheduleTemplate::factory()->create([
            'ferry_id' => $ferry->id,
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'ends_on' => null,
            'departure_time' => '09:00:00',
            'arrival_time' => '10:30:00',
            'available_seats' => 25,
        ]);

        (new ScheduleTemplateGenerator)->generate();

        $this->assertSame(61, FerrySchedule::where('template_id', $template->id)->count());
        $first = FerrySchedule::where('template_id', $template->id)->orderBy('departure_date')->first();
        $this->assertSame('2026-08-01', $first->departure_date->toDateString());
        $this->assertSame('09:00:00', $first->departure_time);
        $this->assertSame(25, $first->available_seats);
        $this->assertSame('scheduled', $first->status);
    }

    public function test_generate_does_not_duplicate_instances_on_rerun(): void
    {
        $template = FerryScheduleTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
        ]);

        $generator = new ScheduleTemplateGenerator;
        $generator->generate();
        $generator->generate();

        $this->assertSame(61, FerrySchedule::where('template_id', $template->id)->count());
    }

    public function test_generate_respects_an_explicit_ends_on_date(): void
    {
        $template = FerryScheduleTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        (new ScheduleTemplateGenerator)->generate();

        $this->assertSame(5, FerrySchedule::where('template_id', $template->id)->count());
    }

    public function test_generate_only_creates_matching_weekdays(): void
    {
        // Weekly on Mondays (ISO 1) only, starting from a Saturday.
        $template = FerryScheduleTemplate::factory()->weekly([1])->create([
            'starts_on' => '2026-08-01', // Saturday
            'ends_on' => '2026-08-14',   // covers Mondays 2026-08-03 and 2026-08-10
        ]);

        (new ScheduleTemplateGenerator)->generate();

        $dates = FerrySchedule::where('template_id', $template->id)->pluck('departure_date')
            ->map(fn ($d) => $d->toDateString())->all();
        $this->assertEqualsCanonicalizing(['2026-08-03', '2026-08-10'], $dates);
    }

    public function test_generate_skips_inactive_templates(): void
    {
        $template = FerryScheduleTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'is_active' => false,
        ]);

        (new ScheduleTemplateGenerator)->generate();

        $this->assertSame(0, FerrySchedule::where('template_id', $template->id)->count());
    }

    public function test_reconcile_syncs_untouched_future_instance_fields_from_template(): void
    {
        $template = FerryScheduleTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'departure_time' => '09:00:00',
            'available_seats' => 25,
        ]);
        $instance = FerrySchedule::factory()->create([
            'ferry_id' => $template->ferry_id,
            'template_id' => $template->id,
            'departure_date' => '2026-08-10',
            'departure_time' => '07:00:00',
            'available_seats' => 25,
        ]);

        $template->update(['departure_time' => '11:00:00', 'available_seats' => 40]);
        (new ScheduleTemplateGenerator)->reconcile();

        $instance->refresh();
        $this->assertSame('11:00:00', $instance->departure_time);
        $this->assertSame(40, $instance->available_seats);
        $this->assertFalse($instance->is_overridden);
    }

    public function test_reconcile_leaves_instance_with_active_tickets_untouched(): void
    {
        $template = FerryScheduleTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'departure_time' => '09:00:00',
        ]);
        $instance = FerrySchedule::factory()->create([
            'ferry_id' => $template->ferry_id,
            'template_id' => $template->id,
            'departure_date' => '2026-08-10',
            'departure_time' => '07:00:00',
        ]);
        FerryTicket::factory()->create(['schedule_id' => $instance->id, 'status' => 'issued']);

        $template->update(['departure_time' => '11:00:00']);
        (new ScheduleTemplateGenerator)->reconcile();

        $instance->refresh();
        $this->assertSame('07:00:00', $instance->departure_time);
        $this->assertFalse($instance->is_overridden);
    }

    public function test_reconcile_flags_instance_whose_date_no_longer_matches_the_rule(): void
    {
        $template = FerryScheduleTemplate::factory()->weekly([1, 5])->create([
            'starts_on' => '2026-08-01',
        ]);
        // 2026-08-07 is a Friday - matched the original rule.
        $instance = FerrySchedule::factory()->create([
            'ferry_id' => $template->ferry_id,
            'template_id' => $template->id,
            'departure_date' => '2026-08-07',
        ]);

        $template->update(['weekdays' => [1]]); // Fridays dropped from the rule
        (new ScheduleTemplateGenerator)->reconcile();

        $instance->refresh();
        $this->assertTrue($instance->is_overridden);
        $this->assertDatabaseHas('ferry_schedules', ['id' => $instance->id, 'status' => 'scheduled']);
    }

    public function test_reconcile_skips_already_overridden_instances(): void
    {
        $template = FerryScheduleTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'departure_time' => '09:00:00',
        ]);
        $instance = FerrySchedule::factory()->create([
            'ferry_id' => $template->ferry_id,
            'template_id' => $template->id,
            'departure_date' => '2026-08-10',
            'departure_time' => '07:00:00',
            'is_overridden' => true,
        ]);

        $template->update(['departure_time' => '11:00:00']);
        (new ScheduleTemplateGenerator)->reconcile();

        $instance->refresh();
        $this->assertSame('07:00:00', $instance->departure_time);
    }
}

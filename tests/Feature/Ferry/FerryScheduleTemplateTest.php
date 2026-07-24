<?php

namespace Tests\Feature\Ferry;

use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryScheduleTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FerryScheduleTemplateTest extends TestCase
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

    public function test_ferry_operator_can_list_templates(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ferry = Ferry::factory()->create();
        FerryScheduleTemplate::factory()->count(2)->create(['ferry_id' => $ferry->id]);

        $response = $this->actingAs($operator)->getJson('/api/ferry/schedule-templates');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_visitor_cannot_list_templates(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $this->actingAs($visitor)->getJson('/api/ferry/schedule-templates')->assertForbidden();
    }

    public function test_ferry_operator_can_create_daily_template_and_instances_generate_immediately(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ferry = Ferry::factory()->create(['capacity' => 30]);

        $response = $this->actingAs($operator)->postJson('/api/ferry/schedule-templates', [
            'ferry_id' => $ferry->id,
            'frequency' => 'daily',
            'departure_time' => '09:00',
            'arrival_time' => '10:30',
            'available_seats' => 30,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $response->assertCreated();
        $templateId = $response->json('id');
        $this->assertSame(5, FerrySchedule::where('template_id', $templateId)->count());
    }

    public function test_creating_a_daily_template_tolerates_blank_weekdays_and_day_of_month(): void
    {
        // The frontend always sends `weekdays` and `day_of_month` in the
        // payload (as [] / '') regardless of which frequency is selected,
        // since only one of the two is ever relevant at a time.
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ferry = Ferry::factory()->create();

        $response = $this->actingAs($operator)->postJson('/api/ferry/schedule-templates', [
            'ferry_id' => $ferry->id,
            'frequency' => 'daily',
            'weekdays' => [],
            'day_of_month' => '',
            'departure_time' => '09:00',
            'arrival_time' => '10:30',
            'available_seats' => 30,
            'starts_on' => '2026-08-01',
            'ends_on' => '',
        ]);

        $response->assertCreated();
    }

    public function test_creating_weekly_template_requires_weekdays(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ferry = Ferry::factory()->create();

        $response = $this->actingAs($operator)->postJson('/api/ferry/schedule-templates', [
            'ferry_id' => $ferry->id,
            'frequency' => 'weekly',
            'departure_time' => '09:00',
            'arrival_time' => '10:30',
            'available_seats' => 30,
            'starts_on' => '2026-08-01',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['weekdays']);
    }

    public function test_visitor_cannot_create_template(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $ferry = Ferry::factory()->create();

        $this->actingAs($visitor)->postJson('/api/ferry/schedule-templates', [
            'ferry_id' => $ferry->id,
            'frequency' => 'daily',
            'departure_time' => '09:00',
            'arrival_time' => '10:30',
            'available_seats' => 30,
            'starts_on' => '2026-08-01',
        ])->assertForbidden();
    }

    public function test_ferry_operator_can_update_template_and_reconciliation_runs_immediately(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $template = FerryScheduleTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'departure_time' => '09:00:00',
        ]);
        $instance = FerrySchedule::factory()->create([
            'ferry_id' => $template->ferry_id,
            'template_id' => $template->id,
            'departure_date' => '2026-08-10',
            'departure_time' => '09:00:00',
        ]);

        $response = $this->actingAs($operator)->patchJson("/api/ferry/schedule-templates/{$template->id}", [
            'departure_time' => '11:00',
        ]);

        $response->assertOk()->assertJsonPath('departure_time', '11:00');
        $this->assertSame('11:00', $instance->fresh()->departure_time);
    }

    public function test_ferry_operator_can_stop_a_template_without_deleting_instances(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $template = FerryScheduleTemplate::factory()->create(['frequency' => 'daily', 'starts_on' => '2026-08-01']);
        $instance = FerrySchedule::factory()->create([
            'ferry_id' => $template->ferry_id,
            'template_id' => $template->id,
            'departure_date' => '2026-08-10',
        ]);

        $this->actingAs($operator)->deleteJson("/api/ferry/schedule-templates/{$template->id}")
            ->assertNoContent();

        $this->assertFalse($template->fresh()->is_active);
        $this->assertDatabaseHas('ferry_schedules', ['id' => $instance->id]);
    }

    public function test_visitor_cannot_update_or_stop_a_template(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $template = FerryScheduleTemplate::factory()->create();

        $this->actingAs($visitor)->patchJson("/api/ferry/schedule-templates/{$template->id}", [
            'departure_time' => '11:00',
        ])->assertForbidden();

        $this->actingAs($visitor)->deleteJson("/api/ferry/schedule-templates/{$template->id}")
            ->assertForbidden();
    }
}

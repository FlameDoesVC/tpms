<?php

namespace Tests\Feature\Ferry;

use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryScheduleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FerryScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_ferries(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        Ferry::factory()->count(2)->create();

        $response = $this->actingAs($user)->getJson('/api/ferries');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_schedules_can_be_filtered_by_date(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $ferry = Ferry::factory()->create();
        FerrySchedule::factory()->create(['ferry_id' => $ferry->id, 'departure_date' => '2026-08-10']);
        FerrySchedule::factory()->create(['ferry_id' => $ferry->id, 'departure_date' => '2026-08-11']);

        $response = $this->actingAs($user)->getJson('/api/ferry/schedules?date=2026-08-10');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_ferry_operator_can_create_schedule(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ferry = Ferry::factory()->create(['capacity' => 30]);

        $response = $this->actingAs($operator)->postJson('/api/ferry/schedules', [
            'ferry_id' => $ferry->id,
            'departure_date' => '2026-09-01',
            'departure_time' => '09:00',
            'arrival_time' => '10:30',
        ]);

        $response->assertCreated()
            ->assertJsonPath('available_seats', 30)
            ->assertJsonPath('status', 'scheduled');
    }

    public function test_visitor_cannot_create_schedule(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $ferry = Ferry::factory()->create();

        $this->actingAs($visitor)->postJson('/api/ferry/schedules', [
            'ferry_id' => $ferry->id,
            'departure_date' => '2026-09-01',
            'departure_time' => '09:00',
            'arrival_time' => '10:30',
        ])->assertForbidden();
    }

    public function test_ferry_operator_can_update_schedule(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $schedule = FerrySchedule::factory()->create();

        $response = $this->actingAs($operator)->patchJson("/api/ferry/schedules/{$schedule->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertOk()->assertJsonPath('status', 'cancelled');
    }

    public function test_ferry_operator_can_delete_schedule(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $schedule = FerrySchedule::factory()->create();

        $this->actingAs($operator)->deleteJson("/api/ferry/schedules/{$schedule->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('ferry_schedules', ['id' => $schedule->id]);
    }

    public function test_updating_a_template_generated_schedule_marks_it_overridden(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $template = FerryScheduleTemplate::factory()->create();
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $template->ferry_id, 'template_id' => $template->id]);

        $response = $this->actingAs($operator)->patchJson("/api/ferry/schedules/{$schedule->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('is_overridden', true);
    }

    public function test_updating_a_manual_schedule_does_not_mark_it_overridden(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $schedule = FerrySchedule::factory()->create();

        $response = $this->actingAs($operator)->patchJson("/api/ferry/schedules/{$schedule->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertOk()->assertJsonPath('is_overridden', false);
    }

    public function test_deleting_a_template_generated_schedule_is_rejected(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $template = FerryScheduleTemplate::factory()->create();
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $template->ferry_id, 'template_id' => $template->id]);

        $this->actingAs($operator)->deleteJson("/api/ferry/schedules/{$schedule->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('ferry_schedules', ['id' => $schedule->id]);
    }
}

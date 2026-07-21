<?php

namespace Tests\Feature\Ferry;

use App\Models\Booking;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FerryTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_with_confirmed_booking_can_purchase_ticket(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed']);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'issued');
        $this->assertDatabaseHas('ferry_tickets', ['user_id' => $visitor->id, 'schedule_id' => $schedule->id]);
        $this->assertEquals(9, $schedule->fresh()->available_seats);
    }

    public function test_purchase_fails_without_a_confirmed_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'pending']);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
        ])->assertUnprocessable();
    }

    public function test_purchase_fails_using_someone_elses_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $other->id, 'status' => 'confirmed']);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
        ])->assertUnprocessable();
    }

    public function test_purchase_fails_when_schedule_is_full(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed']);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 0]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
        ])->assertUnprocessable();
    }

    public function test_visitor_sees_only_their_own_tickets(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        FerryTicket::factory()->create(['user_id' => $visitor->id]);
        FerryTicket::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($visitor)->getJson('/api/ferry/tickets');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_ferry_operator_can_look_up_a_ticket_without_validating_it(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ticket = FerryTicket::factory()->create(['status' => 'issued']);

        $response = $this->actingAs($operator)->getJson("/api/ferry/tickets/{$ticket->id}");

        $response->assertOk()->assertJsonPath('status', 'issued');
        $this->assertEquals('issued', $ticket->fresh()->status);
    }

    public function test_visitor_cannot_look_up_a_ticket(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $ticket = FerryTicket::factory()->create();

        $this->actingAs($visitor)->getJson("/api/ferry/tickets/{$ticket->id}")
            ->assertForbidden();
    }

    public function test_ferry_operator_can_validate_a_ticket(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ticket = FerryTicket::factory()->create(['status' => 'issued']);

        $response = $this->actingAs($operator)->postJson("/api/ferry/tickets/{$ticket->id}/validate");

        $response->assertOk()->assertJsonPath('status', 'used');
    }

    public function test_validating_an_already_used_ticket_fails(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ticket = FerryTicket::factory()->create(['status' => 'used']);

        $this->actingAs($operator)->postJson("/api/ferry/tickets/{$ticket->id}/validate")
            ->assertUnprocessable();
    }

    public function test_visitor_cannot_validate_a_ticket(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $ticket = FerryTicket::factory()->create(['status' => 'issued']);

        $this->actingAs($visitor)->postJson("/api/ferry/tickets/{$ticket->id}/validate")
            ->assertForbidden();
    }

    public function test_ferry_operator_can_list_passengers_for_a_schedule(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $schedule = FerrySchedule::factory()->create();
        FerryTicket::factory()->count(2)->create(['schedule_id' => $schedule->id]);

        $response = $this->actingAs($operator)->getJson("/api/ferry/schedules/{$schedule->id}/passengers");

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_visitor_cannot_list_passengers(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $schedule = FerrySchedule::factory()->create();

        $this->actingAs($visitor)->getJson("/api/ferry/schedules/{$schedule->id}/passengers")
            ->assertForbidden();
    }
}

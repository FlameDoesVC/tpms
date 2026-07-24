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
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 2]);
        $ferry = Ferry::factory()->create(['capacity' => 40, 'price_per_seat' => 20]);
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $ferry->id, 'available_seats' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1, 2],
            'payment_method' => 'online',
        ]);

        $response->assertCreated();
        $this->assertCount(2, $response->json());
        $this->assertEquals('issued', $response->json('0.status'));
        $this->assertEquals('20.00', $response->json('0.price'));
        $this->assertDatabaseHas('ferry_tickets', ['user_id' => $visitor->id, 'schedule_id' => $schedule->id, 'seat_number' => 1]);
        $this->assertDatabaseHas('ferry_tickets', ['user_id' => $visitor->id, 'schedule_id' => $schedule->id, 'seat_number' => 2]);
        $this->assertEquals(8, $schedule->fresh()->available_seats);
    }

    public function test_purchase_fails_without_a_confirmed_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'pending', 'guests_count' => 1]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1],
            'payment_method' => 'online',
        ])->assertUnprocessable();
    }

    public function test_purchase_fails_using_someone_elses_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $other->id, 'status' => 'confirmed', 'guests_count' => 1]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1],
            'payment_method' => 'online',
        ])->assertUnprocessable();
    }

    public function test_purchase_fails_when_schedule_is_full(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 1]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 0]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1],
            'payment_method' => 'online',
        ])->assertUnprocessable();
    }

    public function test_purchase_fails_when_seat_count_exceeds_party_capacity(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 2]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1, 2, 3],
            'payment_method' => 'online',
        ])->assertUnprocessable();
    }

    public function test_purchase_succeeds_with_fewer_seats_than_party_capacity(): void
    {
        // Not every guest has to travel on the same leg - only exceeding the
        // party's total capacity is rejected, not booking under it.
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 3]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1],
            'payment_method' => 'online',
        ]);

        $response->assertCreated();
        $this->assertCount(1, $response->json());
    }

    public function test_purchase_sums_party_capacity_across_a_multi_room_booking(): void
    {
        // Reproduces the reported bug: 2 double rooms for a party of 3 splits
        // into bookings of 2 and 1 guest server-side - a ferry ticket for the
        // whole party (3 seats) must succeed against EITHER room, since both
        // share the same group_booking_id and their capacities are summed.
        $visitor = User::factory()->create()->assignRole('visitor');
        $anchor = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 2]);
        Booking::factory()->create([
            'user_id' => $visitor->id,
            'status' => 'confirmed',
            'guests_count' => 1,
            'group_booking_id' => $anchor->id,
        ]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $anchor->id,
            'seat_numbers' => [1, 2, 3],
            'payment_method' => 'online',
        ]);

        $response->assertCreated();
        $this->assertCount(3, $response->json());
    }

    public function test_purchase_succeeds_topping_up_remaining_party_capacity_for_the_date(): void
    {
        // A party can top up across more than one purchase - e.g. some of
        // the party booked online ahead of time and the rest pay cash
        // walking up to the gate. This must succeed as long as the total
        // issued for the date still fits the party's headcount.
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 2]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);
        FerryTicket::factory()->create(['booking_id' => $booking->id, 'schedule_id' => $schedule->id, 'seat_number' => 1]);

        $response = $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [2],
            'payment_method' => 'cash',
        ]);

        $response->assertCreated();
        $this->assertCount(1, $response->json());
    }

    public function test_purchase_fails_when_topping_up_would_exceed_remaining_capacity_for_the_date(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 2]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);
        FerryTicket::factory()->create(['booking_id' => $booking->id, 'schedule_id' => $schedule->id, 'seat_number' => 1]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [2, 3],
            'payment_method' => 'online',
        ])->assertUnprocessable();
    }

    public function test_purchase_fails_for_a_different_ferry_on_the_same_date_once_party_capacity_is_used_up(): void
    {
        // A party makes one trip per leg - remaining capacity is tracked per
        // DATE, not per exact schedule, so a party that already used up its
        // whole headcount on one boat can't also book a different boat the
        // same day.
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 1]);
        $bookedSchedule = FerrySchedule::factory()->create(['departure_date' => '2026-09-01', 'available_seats' => 10]);
        $otherSchedule = FerrySchedule::factory()->create(['departure_date' => '2026-09-01', 'available_seats' => 10]);
        FerryTicket::factory()->create(['booking_id' => $booking->id, 'schedule_id' => $bookedSchedule->id, 'seat_number' => 1]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $otherSchedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1],
            'payment_method' => 'online',
        ])->assertUnprocessable();
    }

    public function test_purchase_sums_remaining_capacity_across_a_sibling_room_in_the_party(): void
    {
        // Remaining capacity must be computed across the WHOLE party, not
        // just the one booking row a ferry ticket happens to reference - a
        // 2-room purchase shares one party, so a ticket already issued
        // against room A counts against room B's remaining capacity too.
        $visitor = User::factory()->create()->assignRole('visitor');
        $anchor = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 1]);
        $sibling = Booking::factory()->create([
            'user_id' => $visitor->id,
            'status' => 'confirmed',
            'guests_count' => 1,
            'group_booking_id' => $anchor->id,
        ]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);
        FerryTicket::factory()->create(['booking_id' => $anchor->id, 'schedule_id' => $schedule->id, 'seat_number' => 1]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $sibling->id,
            'seat_numbers' => [2, 3],
            'payment_method' => 'online',
        ])->assertUnprocessable();

        $response = $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $sibling->id,
            'seat_numbers' => [2],
            'payment_method' => 'cash',
        ]);

        $response->assertCreated();
    }

    public function test_purchase_fails_when_a_selected_seat_is_already_taken(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 1]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);
        FerryTicket::factory()->create(['schedule_id' => $schedule->id, 'seat_number' => 3, 'status' => 'issued']);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [3],
            'payment_method' => 'cash',
        ])->assertUnprocessable();
    }

    public function test_seat_map_lists_taken_seats(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $ferry = Ferry::factory()->create(['capacity' => 40, 'price_per_seat' => 20]);
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $ferry->id]);
        FerryTicket::factory()->create(['schedule_id' => $schedule->id, 'seat_number' => 5, 'status' => 'issued']);
        FerryTicket::factory()->create(['schedule_id' => $schedule->id, 'seat_number' => 6, 'status' => 'used']);
        FerryTicket::factory()->create(['schedule_id' => $schedule->id, 'seat_number' => 7, 'status' => 'used']);

        $response = $this->actingAs($visitor)->getJson("/api/ferry/schedules/{$schedule->id}/seats");

        $response->assertOk()
            ->assertJsonPath('capacity', 40)
            ->assertJsonPath('price_per_seat', '20.00');
        $this->assertEqualsCanonicalizing([5, 6, 7], $response->json('taken_seats'));
    }

    public function test_seat_map_is_public(): void
    {
        // A guest-checkout visitor may pick seats for a hotel room still in
        // their cart before an account exists - only purchasing needs one.
        $schedule = FerrySchedule::factory()->create();

        $this->getJson("/api/ferry/schedules/{$schedule->id}/seats")->assertOk();
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

    public function test_ferry_operator_can_cancel_a_ticket_and_seat_is_restored(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $schedule = FerrySchedule::factory()->create(['available_seats' => 9]);
        $ticket = FerryTicket::factory()->create(['schedule_id' => $schedule->id, 'status' => 'issued']);

        $response = $this->actingAs($operator)->postJson("/api/ferry/tickets/{$ticket->id}/cancel");

        $response->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertEquals(10, $schedule->fresh()->available_seats);
    }

    public function test_cancelling_an_already_used_ticket_fails(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ticket = FerryTicket::factory()->create(['status' => 'used']);

        $this->actingAs($operator)->postJson("/api/ferry/tickets/{$ticket->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_cancelling_an_already_cancelled_ticket_fails(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ticket = FerryTicket::factory()->create(['status' => 'cancelled']);

        $this->actingAs($operator)->postJson("/api/ferry/tickets/{$ticket->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_validating_a_cancelled_ticket_fails(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ticket = FerryTicket::factory()->create(['status' => 'cancelled']);

        $this->actingAs($operator)->postJson("/api/ferry/tickets/{$ticket->id}/validate")
            ->assertUnprocessable();
    }

    public function test_visitor_cannot_cancel_a_ticket(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $ticket = FerryTicket::factory()->create(['status' => 'issued']);

        $this->actingAs($visitor)->postJson("/api/ferry/tickets/{$ticket->id}/cancel")
            ->assertForbidden();
    }

    public function test_ferry_operator_can_view_party_status_for_a_scanned_hotel_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $anchor = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 2]);
        $sibling = Booking::factory()->create([
            'user_id' => $visitor->id,
            'status' => 'confirmed',
            'guests_count' => 1,
            'group_booking_id' => $anchor->id,
        ]);
        $schedule = FerrySchedule::factory()->create(['departure_date' => '2026-09-01', 'available_seats' => 10]);
        FerryTicket::factory()->create(['booking_id' => $anchor->id, 'schedule_id' => $schedule->id, 'seat_number' => 1]);
        FerryTicket::factory()->create(['booking_id' => $sibling->id, 'schedule_id' => $schedule->id, 'seat_number' => 2]);

        // Scanning the SIBLING room's booking must still surface the whole
        // party's tickets, including the one issued against the anchor room.
        $response = $this->actingAs($operator)->getJson(
            "/api/ferry/bookings/{$sibling->id}/party?schedule_id={$schedule->id}"
        );

        $response->assertOk();
        $this->assertEquals(3, $response->json('party_guests_count'));
        $this->assertCount(2, $response->json('tickets'));
        $this->assertEquals(1, $response->json('remaining_seats'));
    }

    public function test_ferry_operator_can_issue_a_walkup_ticket_on_a_visitors_behalf(): void
    {
        // The operator is NOT the booking's owner - issueTicket (the
        // visitor self-service endpoint) would reject this on an ownership
        // mismatch, which is exactly why the walkup endpoint exists.
        $visitor = User::factory()->create()->assignRole('visitor');
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 2]);
        $ferry = Ferry::factory()->create(['capacity' => 10, 'price_per_seat' => 20]);
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $ferry->id, 'available_seats' => 10]);

        $response = $this->actingAs($operator)->postJson('/api/ferry/tickets/walkup', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1, 2],
            'payment_method' => 'cash',
        ]);

        $response->assertCreated();
        $this->assertCount(2, $response->json());
        $this->assertDatabaseHas('ferry_tickets', ['user_id' => $visitor->id, 'booking_id' => $booking->id, 'seat_number' => 1]);
    }

    public function test_visitor_cannot_issue_a_walkup_ticket(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 1]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);

        $this->actingAs($visitor)->postJson('/api/ferry/tickets/walkup', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [1],
            'payment_method' => 'cash',
        ])->assertForbidden();
    }

    public function test_walkup_ticket_still_respects_remaining_party_capacity(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 1]);
        $schedule = FerrySchedule::factory()->create(['available_seats' => 10]);
        FerryTicket::factory()->create(['booking_id' => $booking->id, 'schedule_id' => $schedule->id, 'seat_number' => 1]);

        $this->actingAs($operator)->postJson('/api/ferry/tickets/walkup', [
            'schedule_id' => $schedule->id,
            'booking_id' => $booking->id,
            'seat_numbers' => [2],
            'payment_method' => 'cash',
        ])->assertUnprocessable();
    }

    public function test_party_status_excludes_tickets_from_a_different_date(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 2]);
        $arrivalSchedule = FerrySchedule::factory()->create(['departure_date' => '2026-09-01', 'available_seats' => 10]);
        $departureSchedule = FerrySchedule::factory()->create(['departure_date' => '2026-09-03', 'available_seats' => 10]);
        FerryTicket::factory()->create(['booking_id' => $booking->id, 'schedule_id' => $arrivalSchedule->id, 'seat_number' => 1]);
        FerryTicket::factory()->create(['booking_id' => $booking->id, 'schedule_id' => $departureSchedule->id, 'seat_number' => 1]);

        $response = $this->actingAs($operator)->getJson(
            "/api/ferry/bookings/{$booking->id}/party?schedule_id={$arrivalSchedule->id}"
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('tickets'));
        $this->assertEquals(1, $response->json('remaining_seats'));
    }

    public function test_visitor_cannot_view_party_status(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed', 'guests_count' => 1]);
        $schedule = FerrySchedule::factory()->create();

        $this->actingAs($visitor)->getJson("/api/ferry/bookings/{$booking->id}/party?schedule_id={$schedule->id}")
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

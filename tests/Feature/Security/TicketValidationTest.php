<?php

namespace Tests\Feature\Security;

use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit findings F-08 (ferry validation ignored the sailing and the date; the
 * only guard was a computed property in the SPA) and F-09 (a cancelled park
 * ticket still validated as used).
 */
class TicketValidationTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::factory()->create()->assignRole('ferry_operator');
    }

    private function parkStaff(): User
    {
        return User::factory()->create()->assignRole('themepark_staff');
    }

    private function sailing(string $date, string $status = 'scheduled'): FerrySchedule
    {
        return FerrySchedule::factory()->create([
            'ferry_id' => Ferry::factory()->create(['capacity' => 40])->id,
            'departure_date' => $date,
            'status' => $status,
            'available_seats' => 40,
        ]);
    }

    /** F-08 */
    public function test_ticket_for_another_sailing_is_rejected(): void
    {
        $boarding = $this->sailing(now()->toDateString());
        $other = $this->sailing(now()->toDateString());

        $ticket = FerryTicket::factory()->create(['schedule_id' => $other->id, 'status' => 'issued']);

        $this->actingAs($this->operator())
            ->postJson("/api/ferry/tickets/{$ticket->id}/validate", ['schedule_id' => $boarding->id])
            ->assertUnprocessable();

        $this->assertSame('issued', $ticket->fresh()->status,
            'F-08: a ticket for a different departure was marked used.');
    }

    /** F-08 */
    public function test_ticket_for_a_past_sailing_is_rejected(): void
    {
        $past = $this->sailing(now()->subDays(10)->toDateString());
        $ticket = FerryTicket::factory()->create(['schedule_id' => $past->id, 'status' => 'issued']);

        $this->actingAs($this->operator())
            ->postJson("/api/ferry/tickets/{$ticket->id}/validate", ['schedule_id' => $past->id])
            ->assertUnprocessable();

        $this->assertSame('issued', $ticket->fresh()->status,
            'F-08: a ten-day-old ticket was accepted at the gate.');
    }

    /** F-08 */
    public function test_ticket_for_a_cancelled_sailing_is_rejected(): void
    {
        $cancelled = $this->sailing(now()->toDateString(), 'cancelled');
        $ticket = FerryTicket::factory()->create(['schedule_id' => $cancelled->id, 'status' => 'issued']);

        $this->actingAs($this->operator())
            ->postJson("/api/ferry/tickets/{$ticket->id}/validate", ['schedule_id' => $cancelled->id])
            ->assertUnprocessable();
    }

    /** F-08: the correct scan must still work. */
    public function test_matching_ticket_for_todays_sailing_validates(): void
    {
        $today = $this->sailing(now()->toDateString());
        $ticket = FerryTicket::factory()->create(['schedule_id' => $today->id, 'status' => 'issued']);

        $this->actingAs($this->operator())
            ->postJson("/api/ferry/tickets/{$ticket->id}/validate", ['schedule_id' => $today->id])
            ->assertOk();

        $this->assertSame('used', $ticket->fresh()->status);
    }

    /** F-09 */
    public function test_cancelled_park_ticket_cannot_be_validated(): void
    {
        $booking = EventBooking::factory()->create([
            'user_id' => null,
            'event_slot_id' => EventSlot::factory()->create(['status' => 'scheduled'])->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($this->parkStaff())
            ->postJson("/api/themepark/tickets/{$booking->id}/validate")
            ->assertUnprocessable();

        $this->assertSame('cancelled', $booking->fresh()->status,
            'F-09: a cancelled park ticket was admitted and marked used.');
    }

    /** F-09 */
    public function test_park_ticket_for_a_cancelled_slot_cannot_be_validated(): void
    {
        $booking = EventBooking::factory()->create([
            'event_slot_id' => EventSlot::factory()->create(['status' => 'cancelled'])->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->parkStaff())
            ->postJson("/api/themepark/tickets/{$booking->id}/validate")
            ->assertUnprocessable();
    }

    /** F-09: the correct scan must still work. */
    public function test_confirmed_park_ticket_validates(): void
    {
        $booking = EventBooking::factory()->create([
            'event_slot_id' => EventSlot::factory()->create(['status' => 'scheduled'])->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->parkStaff())
            ->postJson("/api/themepark/tickets/{$booking->id}/validate")
            ->assertOk();

        $this->assertSame('used', $booking->fresh()->status);
    }
}

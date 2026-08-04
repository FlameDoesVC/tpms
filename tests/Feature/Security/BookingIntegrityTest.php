<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit findings F-02 (a visitor could confirm their own unpaid booking, which
 * is the state that unlocks ferry ticket purchase) and F-05 (re-confirming a
 * cancelled booking double-books a room that has already been resold).
 */
class BookingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function visitor(): User
    {
        return User::factory()->create()->assignRole('visitor');
    }

    /** F-02 */
    public function test_visitor_cannot_confirm_their_own_booking(): void
    {
        $visitor = $this->visitor();
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'pending']);

        $this->actingAs($visitor)
            ->patchJson("/api/bookings/{$booking->id}", ['status' => 'confirmed'])
            ->assertUnprocessable();

        $this->assertSame('pending', $booking->fresh()->status,
            'F-02: a visitor promoted their own unpaid booking to confirmed.');
    }

    /** F-02: staff confirming at the front desk is legitimate and must still work. */
    public function test_hotel_manager_can_still_confirm_a_booking(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->actingAs($manager)
            ->patchJson("/api/bookings/{$booking->id}", ['status' => 'confirmed'])
            ->assertOk();

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    /** F-02: paying is a server-owned action that records a payment. */
    public function test_paying_confirms_the_booking_and_records_a_payment(): void
    {
        $visitor = $this->visitor();
        $booking = Booking::factory()->create([
            'user_id' => $visitor->id,
            'status' => 'pending',
            'total_price' => 450.00,
        ]);

        $this->actingAs($visitor)
            ->postJson('/api/bookings/pay', ['booking_ids' => [$booking->id]])
            ->assertOk();

        $this->assertSame('confirmed', $booking->fresh()->status);

        $payment = Payment::where('payable_id', $booking->id)->first();
        $this->assertNotNull($payment, 'no Payment row was recorded');
        $this->assertSame('450.00', (string) $payment->amount,
            'the amount must come from the booking, not the request');
    }

    /** F-02: the amount must not be attacker-supplied. */
    public function test_paying_ignores_a_client_supplied_amount(): void
    {
        $visitor = $this->visitor();
        $booking = Booking::factory()->create([
            'user_id' => $visitor->id,
            'status' => 'pending',
            'total_price' => 900.00,
        ]);

        $this->actingAs($visitor)->postJson('/api/bookings/pay', [
            'booking_ids' => [$booking->id],
            'amount' => 1,
            'total' => 1,
        ])->assertOk();

        $this->assertSame('900.00', (string) Payment::where('payable_id', $booking->id)->first()->amount);
    }

    public function test_cannot_pay_someone_elses_booking(): void
    {
        $mallory = $this->visitor();
        $victimBooking = Booking::factory()->create(['status' => 'pending']);

        $this->actingAs($mallory)
            ->postJson('/api/bookings/pay', ['booking_ids' => [$victimBooking->id]])
            ->assertForbidden();

        $this->assertSame('pending', $victimBooking->fresh()->status);
    }

    public function test_cannot_pay_a_booking_twice(): void
    {
        $visitor = $this->visitor();
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed']);

        $this->actingAs($visitor)
            ->postJson('/api/bookings/pay', ['booking_ids' => [$booking->id]])
            ->assertUnprocessable();

        $this->assertSame(0, Payment::where('payable_id', $booking->id)->count());
    }

    /** F-05: the exploit that double-books a room. */
    public function test_cancelled_booking_cannot_be_revived(): void
    {
        $visitor = $this->visitor();
        $room = Room::factory()->create(['max_guests' => 4, 'is_available' => true]);

        $booking = Booking::factory()->create([
            'user_id' => $visitor->id,
            'room_id' => $room->id,
            'status' => 'cancelled',
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-05',
        ]);

        $this->actingAs($visitor)
            ->patchJson("/api/bookings/{$booking->id}", ['status' => 'confirmed'])
            ->assertUnprocessable();

        $this->assertSame('cancelled', $booking->fresh()->status,
            'F-05: a cancelled booking was revived, double-booking the room.');
    }

    /** F-05: end to end - the room was resold, so the original must stay dead. */
    public function test_reviving_cannot_double_book_a_resold_room(): void
    {
        $first = $this->visitor();
        $second = $this->visitor();
        $room = Room::factory()->create(['max_guests' => 4, 'is_available' => true]);

        $payload = [
            'room_id' => $room->id,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
            'guests_count' => 2,
        ];

        $firstBookingId = $this->actingAs($first)->postJson('/api/bookings', $payload)->json('0.id');
        $this->actingAs($first)->patchJson("/api/bookings/{$firstBookingId}", ['status' => 'cancelled']);

        // The room is free again, so somebody else legitimately takes it.
        $this->actingAs($second)->postJson('/api/bookings', $payload)->assertCreated();

        // The original holder tries to flip their cancelled booking back.
        $this->actingAs($first)
            ->patchJson("/api/bookings/{$firstBookingId}", ['status' => 'confirmed'])
            ->assertUnprocessable();

        $this->assertSame(
            1,
            Booking::where('room_id', $room->id)->where('status', '!=', 'cancelled')->count(),
            'F-05: two active bookings exist on one room for the same dates.'
        );
    }

    /** Cancelling must remain available to the owner. */
    public function test_owner_can_still_cancel(): void
    {
        $visitor = $this->visitor();
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'confirmed']);

        $this->actingAs($visitor)
            ->patchJson("/api/bookings/{$booking->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}

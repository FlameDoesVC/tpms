<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\EventBooking;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding F-03: reference codes were sprintf('VFN-T%04d', $id), and the
 * code is the entire credential presented at the gate - the scanners parsed the
 * digits straight back into a primary key. A forged sequential code was worth as
 * much as a real ticket, and a guessed booking code returned a stranger's whole
 * party plus their remaining seat allowance.
 */
class ReferenceCodeTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::factory()->create()->assignRole('ferry_operator');
    }

    public function test_codes_are_not_derived_from_the_id(): void
    {
        $bookings = Booking::factory()->count(3)->create();

        foreach ($bookings as $booking) {
            $this->assertMatchesRegularExpression('/^VFN-B[A-Z0-9]{12}$/', $booking->reference_code);
            $this->assertNotSame(sprintf('VFN-B%04d', $booking->id), $booking->reference_code,
                'F-03: the reference code is still derived from the primary key.');
        }

        $this->assertCount(3, $bookings->pluck('reference_code')->unique(),
            'codes must be unique');
    }

    public function test_every_ticketed_model_gets_a_prefixed_code(): void
    {
        $this->assertStringStartsWith('VFN-B', Booking::factory()->create()->reference_code);
        $this->assertStringStartsWith('VFN-T', FerryTicket::factory()->create()->reference_code);
        $this->assertStringStartsWith('VFN-E', EventBooking::factory()->create()->reference_code);
    }

    public function test_a_real_code_resolves_at_the_gate(): void
    {
        $ticket = FerryTicket::factory()->create(['status' => 'issued']);

        $this->actingAs($this->operator())
            ->getJson('/api/ferry/tickets/lookup?code='.$ticket->reference_code)
            ->assertOk()
            ->assertJsonPath('id', $ticket->id);
    }

    public function test_a_forged_sequential_code_is_rejected(): void
    {
        FerryTicket::factory()->create(['status' => 'issued']);

        $this->actingAs($this->operator())
            ->getJson('/api/ferry/tickets/lookup?code=VFN-T0001')
            ->assertNotFound();
    }

    public function test_a_guessed_booking_code_does_not_expose_a_party(): void
    {
        $schedule = FerrySchedule::factory()->create([
            'ferry_id' => Ferry::factory()->create(['capacity' => 20])->id,
            'departure_date' => now()->toDateString(),
        ]);
        Booking::factory()->create(['status' => 'confirmed']);

        $this->actingAs($this->operator())
            ->getJson("/api/ferry/bookings/lookup?code=VFN-B0001&schedule_id={$schedule->id}")
            ->assertNotFound();
    }

    public function test_visitor_cannot_resolve_codes(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $ticket = FerryTicket::factory()->create();

        $this->actingAs($visitor)
            ->getJson('/api/ferry/tickets/lookup?code='.$ticket->reference_code)
            ->assertForbidden();
    }

    public function test_park_code_resolves_for_staff_only(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = EventBooking::factory()->create(['status' => 'confirmed']);

        $this->actingAs($staff)
            ->getJson('/api/themepark/tickets/lookup?code='.$booking->reference_code)
            ->assertOk()
            ->assertJsonPath('id', $booking->id);

        $this->actingAs($visitor)
            ->getJson('/api/themepark/tickets/lookup?code='.$booking->reference_code)
            ->assertForbidden();
    }

    /** The lookup routes must not be shadowed by the {id} wildcards. */
    public function test_lookup_routes_are_not_captured_by_wildcards(): void
    {
        $ticket = FerryTicket::factory()->create();

        $this->actingAs($this->operator())
            ->getJson('/api/ferry/tickets/lookup?code='.$ticket->reference_code)
            ->assertOk();

        $this->actingAs(User::factory()->create()->assignRole('themepark_staff'))
            ->getJson('/api/themepark/tickets/lookup?code='.EventBooking::factory()->create()->reference_code)
            ->assertOk();
    }
}

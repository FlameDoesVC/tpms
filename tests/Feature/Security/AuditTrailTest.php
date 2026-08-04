<?php

namespace Tests\Feature\Security;

use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Audit finding F-19: nothing recorded who validated a ticket, cancelled a
 * booking or changed a role, so none of the exploits in this audit would have
 * left a trace.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_validating_a_ferry_ticket_records_the_operator(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $schedule = FerrySchedule::factory()->create([
            'ferry_id' => Ferry::factory()->create(['capacity' => 20])->id,
            'departure_date' => now()->toDateString(),
        ]);
        $ticket = FerryTicket::factory()->create([
            'schedule_id' => $schedule->id,
            'status' => 'issued',
        ]);

        $this->actingAs($operator)->postJson(
            "/api/ferry/tickets/{$ticket->id}/validate",
            ['schedule_id' => $schedule->id]
        )->assertOk();

        $ticket->refresh();
        $this->assertSame($operator->id, $ticket->validated_by);
        $this->assertNotNull($ticket->validated_at);
    }

    public function test_cancelling_a_ferry_ticket_records_the_operator(): void
    {
        $operator = User::factory()->create()->assignRole('ferry_operator');
        $ticket = FerryTicket::factory()->create(['status' => 'issued']);

        $this->actingAs($operator)
            ->postJson("/api/ferry/tickets/{$ticket->id}/cancel")
            ->assertOk();

        $ticket->refresh();
        $this->assertSame($operator->id, $ticket->cancelled_by);
        $this->assertNotNull($ticket->cancelled_at);
    }

    public function test_validating_a_park_ticket_records_the_staff_member(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $booking = EventBooking::factory()->create([
            'event_slot_id' => EventSlot::factory()->create(['status' => 'scheduled'])->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($staff)
            ->postJson("/api/themepark/tickets/{$booking->id}/validate")
            ->assertOk();

        $booking->refresh();
        $this->assertSame($staff->id, $booking->validated_by);
        $this->assertNotNull($booking->validated_at);
    }

    public function test_cancelling_a_park_booking_records_who_did_it(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $event = ThemeParkEvent::factory()->create(['capacity_per_slot' => 20]);
        $booking = EventBooking::factory()->create([
            'user_id' => $visitor->id,
            'event_slot_id' => EventSlot::factory()->create([
                'event_id' => $event->id,
                'available_capacity' => 10,
                'status' => 'scheduled',
            ])->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($visitor)
            ->deleteJson("/api/themepark/bookings/{$booking->id}")
            ->assertOk();

        $booking->refresh();
        $this->assertSame($visitor->id, $booking->cancelled_by);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_a_failed_login_is_logged(): void
    {
        User::factory()->create(['email' => 'target@example.com'])->assignRole('visitor');

        Log::shouldReceive('channel')->with('audit')->andReturnSelf();
        Log::shouldReceive('info')
            ->withArgs(fn (string $event) => $event === 'auth.login.failed')
            ->atLeast()->once();

        $this->postJson('/login', [
            'email' => 'target@example.com',
            'password' => 'wrong-password',
        ]);
    }

    public function test_a_role_change_is_logged(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $subject = User::factory()->create()->assignRole('visitor');

        Log::shouldReceive('channel')->with('audit')->andReturnSelf();
        Log::shouldReceive('info')
            ->withArgs(fn (string $event) => $event === 'admin.user.role_changed')
            ->atLeast()->once();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$subject->id}", ['role' => 'hotel_manager'])
            ->assertOk();
    }
}

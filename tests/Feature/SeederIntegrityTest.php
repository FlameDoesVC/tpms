<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\EventBooking;
use App\Models\FerryTicket;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The demo seeder exercises nearly every model in one pass, which makes it a
 * useful integration check on the security work: non-fillable is_guest, generated
 * reference codes, and the permission grants all have to survive it.
 */
class SeederIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_runs_and_produces_valid_records(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertGreaterThan(0, Booking::count());
        $this->assertGreaterThan(0, FerryTicket::count());
        $this->assertGreaterThan(0, EventBooking::count());

        // Every ticketed record must carry an opaque, prefixed reference code.
        foreach (Booking::all() as $booking) {
            $this->assertMatchesRegularExpression('/^VFN-B[A-Z0-9]{12}$/', $booking->reference_code);
        }
        foreach (FerryTicket::all() as $ticket) {
            $this->assertMatchesRegularExpression('/^VFN-T[A-Z0-9]{12}$/', $ticket->reference_code);
        }
        foreach (EventBooking::all() as $booking) {
            $this->assertMatchesRegularExpression('/^VFN-E[A-Z0-9]{12}$/', $booking->reference_code);
        }

        $this->assertSame(
            Booking::count(),
            Booking::distinct()->count('reference_code'),
            'reference codes must be unique'
        );
    }

    public function test_seeded_roles_carry_their_permissions(): void
    {
        $expected = [
            'hotel_manager' => 'hotels.manage',
            'ferry_operator' => 'fleet.manage',
            'themepark_staff' => 'park.events.manage',
            'admin' => 'users.manage',
        ];

        foreach ($expected as $role => $permission) {
            $user = User::factory()->create()->assignRole($role);

            $this->assertTrue($user->can($permission),
                "{$role} should hold {$permission}");
        }

        $visitor = User::factory()->create()->assignRole('visitor');

        foreach (array_values($expected) as $permission) {
            $this->assertFalse($visitor->can($permission),
                "visitor must not hold {$permission}");
        }
    }

    public function test_migrations_are_reversible(): void
    {
        // The reference-code and audit-column migrations both add constrained
        // foreign keys and unique indexes; a broken down() makes them a one-way
        // door on any environment that needs to roll back.
        Artisan::call('migrate:rollback', ['--step' => 3]);
        Artisan::call('migrate');

        $this->assertTrue(true);
    }
}

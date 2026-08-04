<?php

namespace Tests\Feature\Security;

use App\Models\EventSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Audit findings F-01 (unthrottled second credential path), F-06 (user
 * provisioning before validation), F-13 (account enumeration) and F-16.
 */
class AuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Establishes a guest session the cheapest way now available to an attacker.
     *
     * A malformed body no longer works (that is F-06's fix), so this uses the
     * smallest *valid* guest-checkout request. The point of F-01 stands: one
     * request buys a session, and every request after it is a free password
     * guess.
     */
    private function establishGuestSession(): void
    {
        $this->postJson('/api/themepark/bookings', [
            'event_slot_id' => EventSlot::factory()->create([
                'available_capacity' => 50,
                'status' => 'scheduled',
            ])->id,
            'ticket_count' => 1,
        ])->assertCreated();
    }

    /** F-01: this endpoint bypassed the 5-attempt lockout that guards /login. */
    public function test_guest_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ])->assignRole('visitor');

        $this->establishGuestSession();

        $lockedOut = false;

        for ($attempt = 1; $attempt <= 15; $attempt++) {
            $response = $this->postJson('/api/guest/login', [
                'email' => 'victim@example.com',
                'password' => "wrong-{$attempt}",
            ]);

            if ($response->status() === 429
                || str_contains(json_encode($response->json()), 'seconds')) {
                $lockedOut = true;
                break;
            }
        }

        $this->assertTrue($lockedOut,
            'F-01: /api/guest/login accepted 15 wrong passwords without locking out.');
    }

    /** F-01: a guest cart merge must never be able to target a staff account. */
    public function test_guest_login_refuses_non_visitor_accounts(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ])->assignRole('admin');

        $this->establishGuestSession();

        $this->postJson('/api/guest/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertForbidden();

        $this->assertFalse(
            User::where('email', 'admin@example.com')->first()->is_guest ?? false,
            'the admin account must be untouched'
        );
    }

    /** F-01: the legitimate path must keep working. */
    public function test_guest_login_still_merges_into_a_visitor_account(): void
    {
        User::factory()->create([
            'email' => 'shopper@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ])->assignRole('visitor');

        $this->establishGuestSession();

        $this->postJson('/api/guest/login', [
            'email' => 'shopper@example.com',
            'password' => 'correct-horse-battery',
        ])->assertOk()->assertJsonPath('user.email', 'shopper@example.com');
    }

    /** F-16 */
    public function test_confirm_password_is_rate_limited(): void
    {
        $user = User::factory()->create()->assignRole('visitor');

        $lockedOut = false;

        for ($attempt = 1; $attempt <= 15; $attempt++) {
            if ($this->actingAs($user)
                ->postJson('/confirm-password', ['password' => "guess-{$attempt}"])
                ->status() === 429) {
                $lockedOut = true;
                break;
            }
        }

        $this->assertTrue($lockedOut,
            'F-16: /confirm-password accepted 15 wrong passwords without throttling.');
    }

    /** F-13: the response must not reveal whether an address is registered. */
    public function test_password_reset_does_not_enumerate_accounts(): void
    {
        User::factory()->create(['email' => 'real@example.com'])->assignRole('visitor');

        $known = $this->postJson('/forgot-password', ['email' => 'real@example.com']);
        $unknown = $this->postJson('/forgot-password', ['email' => 'nobody@example.com']);

        $this->assertSame($known->status(), $unknown->status(),
            'F-13: status differs between a known and an unknown email.');
        $this->assertSame($known->json(), $unknown->json(),
            'F-13: response body differs between a known and an unknown email.');
    }

    /** F-06: a request that fails validation must not leave a user row behind. */
    public function test_malformed_checkout_does_not_provision_a_user(): void
    {
        $before = User::count();

        $this->postJson('/api/cart/checkout', ['items' => 'garbage'])
            ->assertUnprocessable();

        $this->assertSame($before, User::count(),
            'F-06: a validation failure still created a permanent guest user row.');
    }

    /** F-06: the legitimate guest-checkout path must still provision an account. */
    public function test_valid_guest_checkout_still_provisions_an_account(): void
    {
        $before = User::count();

        $this->postJson('/api/themepark/bookings', [
            'event_slot_id' => EventSlot::factory()->create([
                'available_capacity' => 10,
                'status' => 'scheduled',
            ])->id,
            'ticket_count' => 2,
        ])->assertCreated();

        $this->assertSame($before + 1, User::count(),
            'guest checkout must still create exactly one guest account');
        $this->assertSame(1, User::where('is_guest', true)->count());
    }
}

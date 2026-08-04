<?php

namespace Tests\Feature\Security;

use App\Models\EventSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Password::defaults() was never configured, so it resolved to its bare default
 * of min:8 and nothing else - `password`, `12345678` and `qwertyui` were all
 * accepted, including for admin accounts.
 *
 * The replacement policy is length-first with a breach-corpus check and no
 * composition rules. These tests pin both halves: that weak passwords are
 * refused, and that reasonable human-chosen ones are not.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** A password long enough and unremarkable enough to pass. */
    private const GOOD = 'lagoon ferry tuesday';

    private function register(string $password): TestResponse
    {
        return $this->postJson('/register', [
            'name' => 'New Visitor',
            // Lowercase: the register rule requires it, and Str::random() is mixed.
            'email' => 'new'.Str::lower(Str::random(8)).'@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_minimum_length_is_enforced(): void
    {
        $min = config('security.password.min_length');

        $this->register(str_repeat('a', $min - 1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_the_old_eight_character_floor_no_longer_passes(): void
    {
        // Exactly what the unconfigured default allowed.
        $this->register('Ab3!efgh')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_a_breached_password_is_refused(): void
    {
        $this->fakePasswordAsBreached('trustno1 trustno1');

        $this->register('trustno1 trustno1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_a_password_longer_than_bcrypts_limit_is_refused(): void
    {
        // bcrypt truncates at 72 bytes, so accepting these would make two
        // different passwords silently equivalent.
        $this->register(str_repeat('a', 80).' phrase')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    /**
     * The other half of the requirement: the policy must not be so strict that
     * it drives people to "Password1!". A long, memorable, all-lowercase phrase
     * with no digits or symbols has to be accepted.
     */
    public function test_a_plain_lowercase_phrase_is_accepted(): void
    {
        $this->register('lagoon ferry tuesday')
            ->assertCreated();
    }

    public function test_no_composition_rules_are_imposed(): void
    {
        foreach ([
            'correcthorsebattery',     // letters only, no digits or symbols
            'sunset over the reef',    // letters and spaces
            'ferrydeck ferrydeck',     // a repeated word, still long
        ] as $password) {
            // A successful registration logs the user in, and the `guest`
            // middleware would redirect the next attempt.
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            $this->register($password)->assertCreated();
        }
    }

    public function test_the_policy_applies_to_password_reset(): void
    {
        $user = User::factory()->create();
        $token = app('auth.password.broker')->createToken($user);

        $this->postJson('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::GOOD,
            'password_confirmation' => self::GOOD,
        ])->assertOk();
    }

    public function test_the_policy_applies_to_changing_your_own_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('existing pass phrase')]);
        $user->assignRole('visitor');

        $this->actingAs($user)->putJson('/password', [
            'current_password' => 'existing pass phrase',
            'password' => 'qwerty12',
            'password_confirmation' => 'qwerty12',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->actingAs($user)->putJson('/password', [
            'current_password' => 'existing pass phrase',
            'password' => self::GOOD,
            'password_confirmation' => self::GOOD,
        ])->assertNoContent();
    }

    public function test_the_policy_applies_to_admin_created_accounts(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'Weak Staff',
            'email' => 'weak@example.com',
            'password' => 'password',
            'role' => 'hotel_manager',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'Strong Staff',
            'email' => 'strong@example.com',
            'password' => self::GOOD,
            'role' => 'hotel_manager',
        ])->assertCreated();
    }

    public function test_the_policy_applies_to_guest_account_claims(): void
    {
        $this->postJson('/api/themepark/bookings', [
            'event_slot_id' => EventSlot::factory()->create([
                'available_capacity' => 10,
                'status' => 'scheduled',
            ])->id,
            'ticket_count' => 1,
        ])->assertCreated();

        $this->patchJson('/api/guest/claim', [
            'name' => 'Claimed',
            'email' => 'claimed@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->patchJson('/api/guest/claim', [
            'name' => 'Claimed',
            'email' => 'claimed@example.com',
            'password' => self::GOOD,
            'password_confirmation' => self::GOOD,
        ])->assertOk();
    }

    /**
     * The candidate password must never leave this server. The HIBP range API
     * takes only the first five characters of the SHA-1 hash (k-anonymity).
     */
    public function test_only_a_hash_prefix_is_sent_to_the_breach_api(): void
    {
        $password = 'lagoon ferry wednesday';

        $this->register($password)->assertCreated();

        Http::assertSent(function ($request) use ($password) {
            $this->assertStringNotContainsString($password, $request->url());
            $this->assertStringNotContainsString(strtoupper(sha1($password)), $request->url());

            // Five hex characters, and nothing more.
            $this->assertMatchesRegularExpression(
                '#^https://api\.pwnedpasswords\.com/range/[A-F0-9]{5}$#',
                $request->url()
            );

            return true;
        });
    }

    /**
     * An outage at the breach API must not stop people registering. Laravel's
     * verifier treats an unreachable API as "no match", which fails open.
     */
    public function test_registration_still_works_if_the_breach_api_is_down(): void
    {
        $this->fakeBreachApiUnavailable();

        $this->register(self::GOOD)->assertCreated();

        // Confirm the outage was actually exercised, rather than the test passing
        // because the stub never changed.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'pwnedpasswords.com'));
    }

    public function test_the_policy_is_a_single_source_of_truth(): void
    {
        // The SPA renders its strength indicator from these, so a drift between
        // config and page would show the user a rule that is not enforced.
        $response = $this->get('/');

        $response->assertSee('name="password-min-length" content="'
            .config('security.password.min_length').'"', false);
    }
}

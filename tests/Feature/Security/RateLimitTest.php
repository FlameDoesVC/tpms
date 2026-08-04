<?php

namespace Tests\Feature\Security;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding F-07: nothing in this application was rate limited. 60
 * consecutive API reads, 25 registrations and unlimited password guesses all
 * succeeded. `throttle` appeared twice in the codebase, both times on routes
 * that were unreachable.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_rate_limited(): void
    {
        $throttled = false;

        for ($i = 0; $i < 25; $i++) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            $status = $this->postJson('/register', [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])->status();

            if ($status === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled,
            'F-07: 25 consecutive registrations succeeded without throttling.');
    }

    public function test_login_route_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'target@example.com'])->assignRole('visitor');

        $throttled = false;

        for ($i = 0; $i < 25; $i++) {
            if ($this->postJson('/login', [
                'email' => "rotating{$i}@example.com",
                'password' => 'whatever',
            ])->status() === 429) {
                $throttled = true;
                break;
            }
        }

        // Rotating the email defeats the per-email limiter in LoginRequest, so
        // this specifically proves the per-IP route limiter exists.
        $this->assertTrue($throttled,
            'F-07: rotating the email bypassed all login rate limiting.');
    }

    public function test_booking_writes_are_rate_limited(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id, 'max_guests' => 4]);

        $throttled = false;

        for ($i = 0; $i < 30; $i++) {
            $status = $this->actingAs($visitor)->postJson('/api/bookings', [
                'room_id' => $room->id,
                'check_in_date' => now()->addDays(1)->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'guests_count' => 1,
            ])->status();

            if ($status === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled,
            'F-07: 30 consecutive booking writes succeeded without throttling.');
    }

    public function test_anonymous_guest_checkout_is_rate_limited(): void
    {
        $throttled = false;

        for ($i = 0; $i < 30; $i++) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            if ($this->postJson('/api/cart/checkout', ['items' => 'garbage'])->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled,
            'F-06/F-07: the user-provisioning routes were not rate limited.');
    }

    /** A normal amount of traffic must not be throttled. */
    public function test_ordinary_browsing_is_not_throttled(): void
    {
        Hotel::factory()->count(3)->create();

        for ($i = 0; $i < 15; $i++) {
            $this->getJson('/api/hotels')->assertOk();
        }
    }
}

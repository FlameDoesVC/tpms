<?php

namespace Tests\Feature;

use App\Models\EventSlot;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_hotel_booking_creates_and_logs_in_a_guest(): void
    {
        $roomType = RoomType::factory()->create(['max_guests' => 4]);
        $room = Room::factory()->forType($roomType)->create();

        $response = $this->postJson('/api/bookings', [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'guests_count' => 2,
        ]);

        $response->assertCreated();
        $this->assertAuthenticated();

        $guest = User::where('is_guest', true)->first();
        $this->assertNotNull($guest);
        $this->assertTrue($guest->hasRole('visitor'));
        $this->assertDatabaseHas('bookings', ['user_id' => $guest->id, 'room_id' => $room->id]);
    }

    public function test_unauthenticated_themepark_booking_creates_and_logs_in_a_guest(): void
    {
        $slot = EventSlot::factory()->create(['available_capacity' => 10]);

        $response = $this->postJson('/api/themepark/bookings', [
            'event_slot_id' => $slot->id,
            'ticket_count' => 2,
        ]);

        $response->assertCreated();
        $this->assertAuthenticated();

        $guest = User::where('is_guest', true)->first();
        $this->assertNotNull($guest);
        $this->assertTrue($guest->hasRole('visitor'));
    }

    public function test_already_authenticated_user_does_not_get_a_guest_account(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $roomType = RoomType::factory()->create(['max_guests' => 4]);
        $room = Room::factory()->forType($roomType)->create();

        $this->actingAs($user)->postJson('/api/bookings', [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'guests_count' => 2,
        ])->assertCreated();

        $this->assertEquals(0, User::where('is_guest', true)->count());
    }

    public function test_guest_can_claim_their_account(): void
    {
        $roomType = RoomType::factory()->create(['max_guests' => 4]);
        $room = Room::factory()->forType($roomType)->create();

        $this->postJson('/api/bookings', [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'guests_count' => 2,
        ])->assertCreated();

        $response = $this->patchJson('/api/guest/claim', [
            'name' => 'Real Name',
            'email' => 'real@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertOk()->assertJsonPath('user.email', 'real@example.com');

        $guest = User::where('email', 'real@example.com')->first();
        $this->assertFalse($guest->is_guest);
        $this->assertEquals('Real Name', $guest->name);
    }

    public function test_non_guest_cannot_claim(): void
    {
        $user = User::factory()->create()->assignRole('visitor');

        $this->actingAs($user)->patchJson('/api/guest/claim', [
            'name' => 'Real Name',
            'email' => 'real@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ])->assertForbidden();
    }

    public function test_guest_claim_requires_authentication(): void
    {
        $this->patchJson('/api/guest/claim', [
            'name' => 'Real Name',
            'email' => 'real@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ])->assertUnauthorized();
    }

    public function test_guest_can_log_into_an_existing_account_and_bookings_transfer(): void
    {
        $existing = User::factory()->create()->assignRole('visitor');
        $roomType = RoomType::factory()->create(['max_guests' => 4]);
        $room = Room::factory()->forType($roomType)->create();

        $booking = $this->postJson('/api/bookings', [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'guests_count' => 2,
        ])->assertCreated()->json();
        $guestId = $booking[0]['user_id'];

        $response = $this->postJson('/api/guest/login', [
            'email' => $existing->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('user.id', $existing->id);
        $this->assertAuthenticatedAs($existing);

        $this->assertDatabaseHas('bookings', ['id' => $booking[0]['id'], 'user_id' => $existing->id]);
        $this->assertDatabaseMissing('users', ['id' => $guestId]);
    }

    public function test_guest_login_with_wrong_password_keeps_guest_session(): void
    {
        $existing = User::factory()->create()->assignRole('visitor');
        $roomType = RoomType::factory()->create(['max_guests' => 4]);
        $room = Room::factory()->forType($roomType)->create();

        $this->postJson('/api/bookings', [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'guests_count' => 2,
        ])->assertCreated();
        $guestId = User::where('is_guest', true)->first()->id;

        $this->postJson('/api/guest/login', [
            'email' => $existing->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $guestId]);
        $this->assertAuthenticated();
        $this->assertNotEquals($existing->id, auth()->id());
    }

    public function test_non_guest_cannot_use_guest_login(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $existing = User::factory()->create()->assignRole('visitor');

        $this->actingAs($user)->postJson('/api/guest/login', [
            'email' => $existing->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_guest_login_requires_authentication(): void
    {
        $existing = User::factory()->create()->assignRole('visitor');

        $this->postJson('/api/guest/login', [
            'email' => $existing->email,
            'password' => 'password',
        ])->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature\Hotel;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_create_a_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $room = Room::factory()->create(['price_per_night' => 100, 'max_guests' => 4]);

        $response = $this->actingAs($visitor)->postJson('/api/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-04',
            'guests_count' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('total_price', '300.00')
            ->assertJsonPath('status', 'pending');
        $this->assertDatabaseHas('bookings', ['user_id' => $visitor->id, 'room_id' => $room->id]);
    }

    public function test_booking_rejects_overlapping_dates_for_same_room(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $room = Room::factory()->create(['price_per_night' => 100]);
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'confirmed',
            'check_in_date' => '2026-09-10',
            'check_out_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($visitor)->postJson('/api/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-09-12',
            'check_out_date' => '2026-09-14',
            'guests_count' => 1,
        ]);

        $response->assertUnprocessable();
    }

    public function test_booking_rejects_guests_over_room_capacity(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $room = Room::factory()->create(['max_guests' => 2]);

        $this->actingAs($visitor)->postJson('/api/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-03',
            'guests_count' => 5,
        ])->assertUnprocessable();
    }

    public function test_visitor_sees_only_their_own_bookings(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        Booking::factory()->create(['user_id' => $visitor->id]);
        Booking::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($visitor)->getJson('/api/bookings');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_hotel_manager_sees_all_bookings(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        Booking::factory()->count(2)->create();

        $response = $this->actingAs($manager)->getJson('/api/bookings');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_owner_can_cancel_their_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id, 'status' => 'pending']);

        $response = $this->actingAs($visitor)->patchJson("/api/bookings/{$booking->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertOk()->assertJsonPath('status', 'cancelled');
    }

    public function test_cancelling_a_booking_frees_the_room_for_the_same_dates(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $room = Room::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $visitor->id,
            'room_id' => $room->id,
            'status' => 'confirmed',
            'check_in_date' => '2026-10-01',
            'check_out_date' => '2026-10-05',
        ]);

        $this->actingAs($visitor)->patchJson("/api/bookings/{$booking->id}", ['status' => 'cancelled']);

        $this->assertTrue($room->fresh()->isAvailableBetween('2026-10-01', '2026-10-05'));
    }

    public function test_user_cannot_cancel_someone_elses_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $other->id]);

        $this->actingAs($visitor)->patchJson("/api/bookings/{$booking->id}", [
            'status' => 'cancelled',
        ])->assertForbidden();
    }
}

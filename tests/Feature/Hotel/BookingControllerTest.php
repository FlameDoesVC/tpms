<?php

namespace Tests\Feature\Hotel;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $visitor->id]);

        $response = $this->actingAs($visitor)->getJson("/api/bookings/{$booking->id}");

        $response->assertOk()
            ->assertJsonPath('id', $booking->id)
            ->assertJsonPath('room.hotel.id', $booking->room->hotel->id);
    }

    public function test_user_cannot_view_someone_elses_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        $booking = Booking::factory()->create(['user_id' => $other->id]);

        $this->actingAs($visitor)->getJson("/api/bookings/{$booking->id}")
            ->assertForbidden();
    }

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

        $response->assertCreated();
        $this->assertCount(1, $response->json());
        $response->assertJsonPath('0.total_price', '300.00')
            ->assertJsonPath('0.status', 'pending');
        $this->assertDatabaseHas('bookings', ['user_id' => $visitor->id, 'room_id' => $room->id]);
    }

    public function test_visitor_can_book_multiple_rooms_of_the_same_type_at_once(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $rooms = Room::factory()->count(3)->create([
            'hotel_id' => $hotel->id,
            'type' => 'double',
            'price_per_night' => 100,
            'max_guests' => 2,
        ]);

        $response = $this->actingAs($visitor)->postJson('/api/bookings', [
            'room_id' => $rooms->first()->id,
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-04',
            'guests_count' => 5,
            'quantity' => 3,
        ]);

        $response->assertCreated();
        $created = $response->json();
        $this->assertCount(3, $created);
        // 5 guests split across 3 rooms of max_guests 2: 2/2/1
        $this->assertEqualsCanonicalizing([2, 2, 1], array_column($created, 'guests_count'));
        $this->assertEqualsCanonicalizing($rooms->pluck('id')->all(), array_column($created, 'room_id'));
        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_booking_rejects_when_not_enough_rooms_of_type_available(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $rooms = Room::factory()->count(2)->create([
            'hotel_id' => $hotel->id,
            'type' => 'double',
            'price_per_night' => 100,
            'max_guests' => 2,
        ]);

        $this->actingAs($visitor)->postJson('/api/bookings', [
            'room_id' => $rooms->first()->id,
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-04',
            'guests_count' => 4,
            'quantity' => 3,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_rejects_guests_exceeding_quantity_times_capacity(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        Room::factory()->count(3)->create([
            'hotel_id' => $hotel->id,
            'type' => 'double',
            'max_guests' => 2,
        ]);
        $room = Room::where('hotel_id', $hotel->id)->first();

        $this->actingAs($visitor)->postJson('/api/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-04',
            'guests_count' => 7,
            'quantity' => 2,
        ])->assertUnprocessable();
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
        $this->assertNotNull($response->json('data.0.user.name'));
    }

    // A multi-room booking splits guests_count evenly across its sibling
    // rows (see HotelBookingService::create) - a visitor's own list has to
    // expose the party's true total, not just whichever room's own share,
    // or anything downstream that sizes itself off a booking's guest count
    // (e.g. the ferry seat picker) silently undercounts it.
    public function test_visitors_own_bookings_include_the_full_party_guest_count(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $rooms = Room::factory()->count(3)->create([
            'hotel_id' => $hotel->id,
            'type' => 'double',
            'price_per_night' => 100,
            'max_guests' => 2,
        ]);

        $created = $this->actingAs($visitor)->postJson('/api/bookings', [
            'room_id' => $rooms->first()->id,
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-04',
            'guests_count' => 5,
            'quantity' => 3,
        ])->json();
        // 5 guests split across 3 rooms: 2/2/1, none of which is the true total.

        $response = $this->actingAs($visitor)->getJson('/api/bookings');

        $response->assertOk();
        $byId = collect($response->json('data'))->keyBy('id');
        foreach ($created as $room) {
            $this->assertSame(5, $byId[$room['id']]['party_guests_count']);
        }
    }

    public function test_hotel_managers_booking_list_omits_party_guest_count(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        Booking::factory()->create();

        $response = $this->actingAs($manager)->getJson('/api/bookings');

        $response->assertOk();
        $this->assertArrayNotHasKey('party_guests_count', $response->json('data.0'));
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

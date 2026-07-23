<?php

namespace Tests\Feature\Hotel;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_rooms_for_a_hotel(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        Room::factory()->count(3)->create(['hotel_id' => $hotel->id]);

        $response = $this->actingAs($user)->getJson("/api/hotels/{$hotel->id}/rooms");

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_index_excludes_rooms_with_overlapping_booking_when_dates_given(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $bookedRoom = Room::factory()->create(['hotel_id' => $hotel->id]);
        $freeRoom = Room::factory()->create(['hotel_id' => $hotel->id]);

        Booking::factory()->create([
            'room_id' => $bookedRoom->id,
            'status' => 'confirmed',
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-15',
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/hotels/{$hotel->id}/rooms?check_in_date=2026-08-12&check_out_date=2026-08-14"
        );

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($freeRoom->id));
        $this->assertFalse($ids->contains($bookedRoom->id));
    }

    public function test_room_types_groups_rooms_by_type_price_and_capacity(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        Room::factory()->count(3)->create([
            'hotel_id' => $hotel->id,
            'type' => 'double',
            'price_per_night' => 100,
            'max_guests' => 2,
        ]);
        Room::factory()->create([
            'hotel_id' => $hotel->id,
            'type' => 'suite',
            'price_per_night' => 250,
            'max_guests' => 4,
        ]);

        $response = $this->actingAs($user)->getJson("/api/hotels/{$hotel->id}/room-types");

        $response->assertOk();
        $groups = collect($response->json());
        $this->assertCount(2, $groups);
        $double = $groups->firstWhere('type', 'double');
        $this->assertEquals(3, $double['available_count']);
        $suite = $groups->firstWhere('type', 'suite');
        $this->assertEquals(1, $suite['available_count']);
    }

    public function test_room_types_excludes_rooms_taken_for_the_given_dates(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $rooms = Room::factory()->count(2)->create([
            'hotel_id' => $hotel->id,
            'type' => 'double',
            'price_per_night' => 100,
            'max_guests' => 2,
        ]);
        Booking::factory()->create([
            'room_id' => $rooms->first()->id,
            'status' => 'confirmed',
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-15',
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/hotels/{$hotel->id}/room-types?check_in_date=2026-08-12&check_out_date=2026-08-14"
        );

        $response->assertOk();
        $groups = collect($response->json());
        $this->assertEquals(1, $groups->firstWhere('type', 'double')['available_count']);
    }

    public function test_room_types_hides_fully_booked_type_entirely(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create([
            'hotel_id' => $hotel->id,
            'type' => 'double',
        ]);
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'confirmed',
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-15',
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/hotels/{$hotel->id}/room-types?check_in_date=2026-08-12&check_out_date=2026-08-14"
        );

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_hotel_manager_can_create_room(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();

        $response = $this->actingAs($manager)->postJson("/api/hotels/{$hotel->id}/rooms", [
            'room_number' => '101',
            'type' => 'double',
            'price_per_night' => 120.50,
            'max_guests' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('room_number', '101')
            ->assertJsonPath('is_available', true);
        $this->assertDatabaseHas('rooms', ['room_number' => '101', 'hotel_id' => $hotel->id]);
    }

    public function test_visitor_cannot_create_room(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();

        $this->actingAs($visitor)->postJson("/api/hotels/{$hotel->id}/rooms", [
            'room_number' => '101',
            'type' => 'double',
            'price_per_night' => 120.50,
            'max_guests' => 2,
        ])->assertForbidden();
    }

    public function test_hotel_manager_can_update_room(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $room = Room::factory()->create(['is_available' => true]);

        $response = $this->actingAs($manager)->patchJson("/api/rooms/{$room->id}", [
            'is_available' => false,
        ]);

        $response->assertOk()->assertJsonPath('is_available', false);
    }

    public function test_hotel_manager_can_delete_room(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $room = Room::factory()->create();

        $this->actingAs($manager)->deleteJson("/api/rooms/{$room->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_visitor_cannot_delete_room(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $room = Room::factory()->create();

        $this->actingAs($visitor)->deleteJson("/api/rooms/{$room->id}")
            ->assertForbidden();
    }
}

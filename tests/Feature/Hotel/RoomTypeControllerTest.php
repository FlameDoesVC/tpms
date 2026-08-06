<?php

namespace Tests\Feature\Hotel;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Room types are what the hotel detail page lists and books from. They used to
 * be synthesised per request by grouping rooms on type|price|capacity, which
 * left nowhere to hang a photo, a description or an amenity list.
 */
class RoomTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_a_hotels_room_types_with_available_counts(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();

        $double = RoomType::factory()->create([
            'hotel_id' => $hotel->id,
            'name' => 'Ocean Double',
            'price_per_night' => 100,
            'max_guests' => 2,
        ]);
        Room::factory()->count(3)->forType($double)->create();

        $suite = RoomType::factory()->create([
            'hotel_id' => $hotel->id,
            'name' => 'Beach Suite',
            'price_per_night' => 250,
            'max_guests' => 4,
        ]);
        Room::factory()->forType($suite)->create();

        $response = $this->actingAs($user)->getJson("/api/hotels/{$hotel->id}/room-types");

        $response->assertOk();
        $types = collect($response->json());
        $this->assertCount(2, $types);
        $this->assertEquals(3, $types->firstWhere('name', 'Ocean Double')['available_count']);
        $this->assertEquals(1, $types->firstWhere('name', 'Beach Suite')['available_count']);
    }

    public function test_index_discounts_rooms_taken_for_the_given_dates(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id]);
        $rooms = Room::factory()->count(2)->forType($roomType)->create();

        Booking::factory()->create([
            'room_id' => $rooms->first()->id,
            'status' => 'confirmed',
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-15',
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/hotels/{$hotel->id}/room-types?check_in_date=2026-08-12&check_out_date=2026-08-14"
        );

        $response->assertOk()->assertJsonPath('0.available_count', 1);
    }

    /**
     * The old grouping endpoint dropped a sold-out type from the response
     * entirely. A detail page has to say "fully booked" where the room is,
     * rather than quietly shortening the list.
     */
    public function test_index_reports_a_fully_booked_type_as_zero_rather_than_omitting_it(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id]);
        $room = Room::factory()->forType($roomType)->create();

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
        $this->assertCount(1, $response->json());
        $response->assertJsonPath('0.available_count', 0);
    }

    public function test_index_omits_inactive_types_from_the_visitor_list(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Live', 'is_active' => true]);
        RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Retired', 'is_active' => false]);

        $response = $this->actingAs($user)->getJson("/api/hotels/{$hotel->id}/room-types");

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Live'));
        $this->assertFalse($names->contains('Retired'));
    }

    // Without deactivated types, the management screen would have no way to
    // bring one back.
    public function test_manager_can_see_inactive_types_with_all(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();
        RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Retired', 'is_active' => false]);

        $response = $this->actingAs($manager)->getJson("/api/hotels/{$hotel->id}/room-types?all=1");

        $response->assertOk()->assertJsonPath('0.name', 'Retired');
    }

    public function test_manager_can_create_a_room_type(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();

        $response = $this->actingAs($manager)->postJson("/api/hotels/{$hotel->id}/room-types", [
            'name' => 'Garden Villa',
            'description' => 'Opens onto the garden path.',
            'price_per_night' => 180,
            'max_guests' => 3,
            'amenities' => ['wifi', 'balcony'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Garden Villa')
            ->assertJsonPath('amenities', ['wifi', 'balcony']);
        $this->assertDatabaseHas('room_types', ['name' => 'Garden Villa', 'hotel_id' => $hotel->id]);
    }

    public function test_amenities_outside_the_catalog_are_rejected(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();

        $this->actingAs($manager)->postJson("/api/hotels/{$hotel->id}/room-types", [
            'name' => 'Garden Villa',
            'price_per_night' => 180,
            'max_guests' => 3,
            'amenities' => ['helipad'],
        ])->assertUnprocessable()->assertJsonValidationErrors('amenities.0');
    }

    public function test_visitor_cannot_create_a_room_type(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();

        $this->actingAs($visitor)->postJson("/api/hotels/{$hotel->id}/room-types", [
            'name' => 'Garden Villa',
            'price_per_night' => 180,
            'max_guests' => 3,
        ])->assertForbidden();
    }

    public function test_manager_can_update_a_room_type(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $roomType = RoomType::factory()->create(['price_per_night' => 100]);

        $this->actingAs($manager)->patchJson("/api/room-types/{$roomType->id}", [
            'price_per_night' => 145,
        ])->assertOk()->assertJsonPath('price_per_night', '145.00');
    }

    public function test_two_room_types_in_one_hotel_cannot_share_a_name(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();
        RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Ocean Double']);

        $this->actingAs($manager)->postJson("/api/hotels/{$hotel->id}/room-types", [
            'name' => 'Ocean Double',
            'price_per_night' => 100,
            'max_guests' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    /**
     * Deleting would cascade the rooms away and their bookings with them.
     */
    public function test_a_room_type_with_rooms_cannot_be_deleted(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $roomType = RoomType::factory()->create();
        Room::factory()->forType($roomType)->create();

        $this->actingAs($manager)->deleteJson("/api/room-types/{$roomType->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('room_types', ['id' => $roomType->id]);
    }

    public function test_an_empty_room_type_can_be_deleted(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $roomType = RoomType::factory()->create();

        $this->actingAs($manager)->deleteJson("/api/room-types/{$roomType->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('room_types', ['id' => $roomType->id]);
    }

    public function test_cover_image_is_served_from_the_sanitised_rendition(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();

        $response = $this->actingAs($manager)->postJson("/api/hotels/{$hotel->id}/room-types", [
            'name' => 'Garden Villa',
            'price_per_night' => 180,
            'max_guests' => 3,
            'image' => UploadedFile::fake()->image('room.jpg', 100, 100),
        ]);

        $response->assertCreated();
        $this->assertStringContainsString('display', $response->json('image_url'));
    }
}

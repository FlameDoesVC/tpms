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

class HotelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_hotels(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        Hotel::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/hotels');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_unauthenticated_visitor_can_list_hotels(): void
    {
        Hotel::factory()->count(2)->create();

        $this->getJson('/api/hotels')->assertOk();
    }

    public function test_hotel_list_defaults_to_twelve_per_page(): void
    {
        Hotel::factory()->count(15)->create();

        $response = $this->getJson('/api/hotels');

        $response->assertOk();
        $this->assertCount(12, $response->json('data'));
        $this->assertSame(15, $response->json('meta.total'));
    }

    // The visitor browse page asks for every hotel in one pass, because its
    // ?hotel=<id> deep links scroll to a section that must already be rendered.
    public function test_hotel_list_accepts_a_larger_per_page(): void
    {
        Hotel::factory()->count(15)->create();

        $response = $this->getJson('/api/hotels?per_page=100');

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
    }

    public function test_hotel_list_caps_per_page_at_one_hundred(): void
    {
        Hotel::factory()->count(3)->create();

        $response = $this->getJson('/api/hotels?per_page=5000');

        $response->assertOk();
        $this->assertSame(100, $response->json('meta.per_page'));
    }

    /**
     * This is the hotel detail page's only request, so everything the page
     * renders has to come back in one response.
     */
    public function test_show_returns_the_detail_payload(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create([
            'facilities' => ['wifi', 'pool'],
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ]);
        $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Ocean Double']);
        Room::factory()->count(2)->forType($roomType)->create();

        $response = $this->actingAs($user)->getJson("/api/hotels/{$hotel->id}");

        $response->assertOk()
            ->assertJsonPath('id', $hotel->id)
            ->assertJsonPath('facilities', ['wifi', 'pool'])
            ->assertJsonPath('check_in_time', '14:00')
            ->assertJsonPath('gallery', [])
            ->assertJsonCount(1, 'room_types')
            ->assertJsonPath('room_types.0.name', 'Ocean Double')
            ->assertJsonPath('room_types.0.available_count', 2);
    }

    public function test_show_reports_availability_for_the_requested_stay(): void
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

        $this->actingAs($user)
            ->getJson("/api/hotels/{$hotel->id}?check_in_date=2026-08-12&check_out_date=2026-08-14")
            ->assertOk()
            ->assertJsonPath('room_types.0.available_count', 1);
    }

    public function test_show_rejects_a_checkout_before_its_checkin(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/hotels/{$hotel->id}?check_in_date=2026-08-14&check_out_date=2026-08-12")
            ->assertUnprocessable();
    }

    public function test_show_omits_inactive_room_types(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Live']);
        RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Retired', 'is_active' => false]);

        $this->actingAs($user)->getJson("/api/hotels/{$hotel->id}")
            ->assertOk()
            ->assertJsonCount(1, 'room_types')
            ->assertJsonPath('room_types.0.name', 'Live');
    }

    public function test_hotel_accepts_detail_fields(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');

        $this->actingAs($manager)->postJson('/api/hotels', [
            'name' => 'Reef House',
            'address' => '3 Reef Rd',
            'total_rooms' => 10,
            'facilities' => ['wifi', 'dive_center'],
            'check_in_time' => '15:00',
            'check_out_time' => '11:00',
            'phone' => '+960 664 0000',
            'email' => 'stay@reefhouse.example',
            'website' => 'https://reefhouse.example',
        ])->assertCreated()
            ->assertJsonPath('facilities', ['wifi', 'dive_center'])
            ->assertJsonPath('phone', '+960 664 0000');
    }

    // The frontend renders each slug as a labelled icon, so anything outside the
    // catalog would come back as a blank chip.
    public function test_facilities_outside_the_catalog_are_rejected(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');

        $this->actingAs($manager)->postJson('/api/hotels', [
            'name' => 'Reef House',
            'address' => '3 Reef Rd',
            'total_rooms' => 10,
            'facilities' => ['private_submarine'],
        ])->assertUnprocessable()->assertJsonValidationErrors('facilities.0');
    }

    public function test_hotel_manager_can_create_hotel(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');

        $response = $this->actingAs($manager)->postJson('/api/hotels', [
            'name' => 'Sunset Resort',
            'description' => 'A nice place',
            'address' => '1 Beach Rd',
            'total_rooms' => 20,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Sunset Resort')
            ->assertJsonPath('is_active', true);
        $this->assertDatabaseHas('hotels', ['name' => 'Sunset Resort']);
    }

    public function test_visitor_cannot_create_hotel(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $response = $this->actingAs($visitor)->postJson('/api/hotels', [
            'name' => 'Sunset Resort',
            'address' => '1 Beach Rd',
            'total_rooms' => 20,
        ]);

        $response->assertForbidden();
    }

    public function test_hotel_manager_can_upload_a_hotel_image(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();

        $response = $this->actingAs($manager)->post("/api/hotels/{$hotel->id}", [
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('resort.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('image_url'));
        $this->assertCount(1, $hotel->fresh()->getMedia('image'));
    }

    public function test_a_non_image_file_is_rejected_for_a_hotel(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();

        $this->actingAs($manager)->post("/api/hotels/{$hotel->id}", [
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertInvalid(['image']);

        $this->assertCount(0, $hotel->fresh()->getMedia('image'));
    }

    public function test_hotel_manager_can_remove_a_hotel_image(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();
        $hotel->addMedia(UploadedFile::fake()->image('resort.jpg'))->toMediaCollection('image');

        $response = $this->actingAs($manager)->patchJson("/api/hotels/{$hotel->id}", [
            'remove_image' => true,
        ]);

        $response->assertOk()->assertJsonPath('image_url', null);
        $this->assertCount(0, $hotel->fresh()->getMedia('image'));
    }

    public function test_hotel_manager_can_update_hotel(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($manager)->patchJson("/api/hotels/{$hotel->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJsonPath('name', 'New Name');
    }

    public function test_visitor_cannot_update_hotel(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();

        $this->actingAs($visitor)
            ->patchJson("/api/hotels/{$hotel->id}", ['name' => 'New Name'])
            ->assertForbidden();
    }

    public function test_hotel_manager_can_delete_hotel(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        $hotel = Hotel::factory()->create();

        $this->actingAs($manager)->deleteJson("/api/hotels/{$hotel->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('hotels', ['id' => $hotel->id]);
    }
}

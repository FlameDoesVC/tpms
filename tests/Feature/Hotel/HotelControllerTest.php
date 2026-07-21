<?php

namespace Tests\Feature\Hotel;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_show_returns_hotel_with_rooms(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        Room::factory()->count(2)->create(['hotel_id' => $hotel->id]);

        $response = $this->actingAs($user)->getJson("/api/hotels/{$hotel->id}");

        $response->assertOk()
            ->assertJsonPath('id', $hotel->id)
            ->assertJsonCount(2, 'rooms');
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

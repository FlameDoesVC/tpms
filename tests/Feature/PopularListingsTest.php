<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\ThemeParkEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopularListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_popular_hotels_are_ordered_by_booking_count(): void
    {
        $quiet = Hotel::factory()->create(['name' => 'Quiet Inn']);
        $popular = Hotel::factory()->create(['name' => 'Busy Resort']);

        $quietRoom = Room::factory()->create(['hotel_id' => $quiet->id]);
        $popularRoom = Room::factory()->create(['hotel_id' => $popular->id]);

        Booking::factory()->create(['room_id' => $quietRoom->id]);
        Booking::factory()->count(3)->create(['room_id' => $popularRoom->id]);

        $response = $this->getJson('/api/hotels/popular');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertEquals('Busy Resort', $names->first());
        $this->assertEquals(3, collect($response->json())->firstWhere('name', 'Busy Resort')['bookings_count']);
    }

    public function test_popular_hotels_endpoint_is_public(): void
    {
        Hotel::factory()->create();

        $this->getJson('/api/hotels/popular')->assertOk();
    }

    public function test_popular_hotels_excludes_cancelled_bookings_from_the_count(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Booking::factory()->create(['room_id' => $room->id, 'status' => 'cancelled']);

        $response = $this->getJson('/api/hotels/popular');

        $response->assertOk();
        $entry = collect($response->json())->firstWhere('id', $hotel->id);
        $this->assertEquals(0, $entry['bookings_count']);
    }

    public function test_popular_themepark_events_are_ordered_by_booking_count(): void
    {
        $quiet = ThemeParkEvent::factory()->create(['name' => 'Quiet Ride']);
        $popular = ThemeParkEvent::factory()->create(['name' => 'Busy Ride']);

        $quietSlot = EventSlot::factory()->create(['event_id' => $quiet->id]);
        $popularSlot = EventSlot::factory()->create(['event_id' => $popular->id]);

        EventBooking::factory()->create(['event_slot_id' => $quietSlot->id]);
        EventBooking::factory()->count(3)->create(['event_slot_id' => $popularSlot->id]);

        $response = $this->getJson('/api/themepark/events/popular');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertEquals('Busy Ride', $names->first());
        $this->assertEquals(3, collect($response->json())->firstWhere('name', 'Busy Ride')['bookings_count']);
    }

    public function test_popular_themepark_events_endpoint_is_public(): void
    {
        ThemeParkEvent::factory()->create();

        $this->getJson('/api/themepark/events/popular')->assertOk();
    }
}

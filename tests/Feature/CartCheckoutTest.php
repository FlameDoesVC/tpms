<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryTicket;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_a_mixed_cart_in_one_go(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $room = Room::factory()->create(['price_per_night' => 100, 'max_guests' => 4]);
        $event = ThemeParkEvent::factory()->create(['price_per_ticket' => 10]);
        $slot = EventSlot::factory()->create(['event_id' => $event->id, 'available_capacity' => 5]);

        $response = $this->actingAs($visitor)->postJson('/api/cart/checkout', [
            'items' => [
                [
                    'id' => 'cart-hotel-1',
                    'type' => 'hotel',
                    'representativeRoomId' => $room->id,
                    'checkIn' => '2026-09-01',
                    'checkOut' => '2026-09-03',
                    'guestsCount' => 2,
                    'quantity' => 1,
                ],
                [
                    'id' => 'cart-park-1',
                    'type' => 'themepark',
                    'slotId' => $slot->id,
                    'ticketCount' => 2,
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertCount(1, $response->json('hotel'));
        $this->assertCount(1, $response->json('themepark'));
        $this->assertEquals('confirmed', $response->json('hotel.0.status'));
        $this->assertDatabaseHas('bookings', ['user_id' => $visitor->id, 'room_id' => $room->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('event_bookings', ['user_id' => $visitor->id, 'event_slot_id' => $slot->id, 'ticket_count' => 2]);
        $this->assertEquals(3, $slot->fresh()->available_capacity);
    }

    public function test_checkout_creates_a_ferry_ticket_linked_to_a_hotel_item_in_the_same_cart(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $room = Room::factory()->create(['price_per_night' => 100, 'max_guests' => 2]);
        $ferry = Ferry::factory()->create(['capacity' => 10, 'price_per_seat' => 20]);
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $ferry->id, 'available_seats' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/cart/checkout', [
            'items' => [
                [
                    'id' => 'cart-hotel-1',
                    'type' => 'hotel',
                    'representativeRoomId' => $room->id,
                    'checkIn' => '2026-09-01',
                    'checkOut' => '2026-09-03',
                    'guestsCount' => 2,
                    'quantity' => 1,
                ],
                [
                    'id' => 'cart-ferry-1',
                    'type' => 'ferry',
                    'scheduleId' => $schedule->id,
                    'hotelCartItemId' => 'cart-hotel-1',
                    'seatNumbers' => [1, 2],
                    'paymentMethod' => 'online',
                ],
            ],
        ]);

        $response->assertCreated();
        $bookingId = $response->json('hotel.0.id');
        $this->assertCount(2, $response->json('ferry'));
        $this->assertDatabaseHas('ferry_tickets', ['booking_id' => $bookingId, 'seat_number' => 1]);
        $this->assertDatabaseHas('ferry_tickets', ['booking_id' => $bookingId, 'seat_number' => 2]);
    }

    public function test_checkout_rolls_back_everything_when_one_item_fails(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $room = Room::factory()->create(['price_per_night' => 100, 'max_guests' => 4]);
        $event = ThemeParkEvent::factory()->create();
        $slot = EventSlot::factory()->create(['event_id' => $event->id, 'available_capacity' => 1]);

        $response = $this->actingAs($visitor)->postJson('/api/cart/checkout', [
            'items' => [
                [
                    'id' => 'cart-hotel-1',
                    'type' => 'hotel',
                    'representativeRoomId' => $room->id,
                    'checkIn' => '2026-09-01',
                    'checkOut' => '2026-09-03',
                    'guestsCount' => 2,
                    'quantity' => 1,
                ],
                [
                    // Exceeds the slot's capacity of 1 - this item must fail.
                    'id' => 'cart-park-1',
                    'type' => 'themepark',
                    'slotId' => $slot->id,
                    'ticketCount' => 5,
                ],
            ],
        ]);

        $response->assertUnprocessable();
        // The hotel booking that would have succeeded must NOT exist either.
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('event_bookings', 0);
        $this->assertEquals(1, $slot->fresh()->available_capacity);
    }

    public function test_checkout_rolls_back_hotel_booking_when_linked_ferry_ticket_fails(): void
    {
        // A party of 2 booking 2 single rooms splits into 2 bookings of 1
        // guest each server-side, but they share one party (guest capacity
        // sums to 2). Asking for MORE seats than that total must still fail,
        // and must not leave a dangling confirmed hotel booking behind.
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        Room::factory()->count(2)->create([
            'hotel_id' => $hotel->id,
            'type' => 'single',
            'max_guests' => 1,
        ]);
        $room = Room::where('hotel_id', $hotel->id)->first();
        $ferry = Ferry::factory()->create(['capacity' => 10, 'price_per_seat' => 20]);
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $ferry->id, 'available_seats' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/cart/checkout', [
            'items' => [
                [
                    'id' => 'cart-hotel-1',
                    'type' => 'hotel',
                    'representativeRoomId' => $room->id,
                    'checkIn' => '2026-09-01',
                    'checkOut' => '2026-09-03',
                    'guestsCount' => 2,
                    'quantity' => 2,
                ],
                [
                    'id' => 'cart-ferry-1',
                    'type' => 'ferry',
                    'scheduleId' => $schedule->id,
                    'hotelCartItemId' => 'cart-hotel-1',
                    'seatNumbers' => [1, 2, 3],
                    'paymentMethod' => 'online',
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('ferry_tickets', 0);
        $this->assertEquals(10, $schedule->fresh()->available_seats);
    }

    public function test_checkout_sums_party_capacity_across_a_multi_room_hotel_item(): void
    {
        // Reproduces the reported bug: 2 double rooms (max_guests=2) for a
        // party of 3 splits into bookings of 2 and 1 guest (intdiv(3,2)=1,
        // extra=1 goes to the first room) - the ferry ticket for the whole
        // party (3 seats) must succeed against the group's combined
        // capacity, not fail against whichever single room it references.
        $visitor = User::factory()->create()->assignRole('visitor');
        $hotel = Hotel::factory()->create();
        Room::factory()->count(2)->create([
            'hotel_id' => $hotel->id,
            'type' => 'double',
            'max_guests' => 2,
            'price_per_night' => 150,
        ]);
        $room = Room::where('hotel_id', $hotel->id)->first();
        $ferry = Ferry::factory()->create(['capacity' => 10, 'price_per_seat' => 20]);
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $ferry->id, 'available_seats' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/cart/checkout', [
            'items' => [
                [
                    'id' => 'cart-hotel-1',
                    'type' => 'hotel',
                    'representativeRoomId' => $room->id,
                    'checkIn' => '2026-09-01',
                    'checkOut' => '2026-09-03',
                    'guestsCount' => 3,
                    'quantity' => 2,
                ],
                [
                    'id' => 'cart-ferry-1',
                    'type' => 'ferry',
                    'scheduleId' => $schedule->id,
                    'hotelCartItemId' => 'cart-hotel-1',
                    'seatNumbers' => [1, 2, 3],
                    'paymentMethod' => 'online',
                ],
            ],
        ]);

        $response->assertCreated();
        $bookings = $response->json('hotel');
        $this->assertCount(2, $bookings);
        $this->assertCount(3, $response->json('ferry'));
        $this->assertDatabaseHas('ferry_tickets', ['booking_id' => $bookings[0]['id'], 'seat_number' => 1]);
        $this->assertDatabaseHas('ferry_tickets', ['booking_id' => $bookings[0]['id'], 'seat_number' => 2]);
        $this->assertDatabaseHas('ferry_tickets', ['booking_id' => $bookings[0]['id'], 'seat_number' => 3]);
    }

    public function test_checkout_fails_when_ferry_item_has_no_resolvable_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $ferry = Ferry::factory()->create(['capacity' => 10, 'price_per_seat' => 20]);
        $schedule = FerrySchedule::factory()->create(['ferry_id' => $ferry->id, 'available_seats' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/cart/checkout', [
            'items' => [
                [
                    'id' => 'cart-ferry-1',
                    'type' => 'ferry',
                    'scheduleId' => $schedule->id,
                    'hotelCartItemId' => 'does-not-exist',
                    'seatNumbers' => [1],
                    'paymentMethod' => 'online',
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('ferry_tickets', 0);
    }
}

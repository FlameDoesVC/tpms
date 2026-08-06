<?php

namespace Tests\Feature\ThemePark;

use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThemeParkEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_events(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        ThemeParkEvent::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/themepark/events');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_show_returns_event_with_slots_for_a_date(): void
    {
        $user = User::factory()->create()->assignRole('visitor');
        $event = ThemeParkEvent::factory()->create();
        EventSlot::factory()->create(['event_id' => $event->id, 'slot_date' => '2026-08-10']);
        EventSlot::factory()->create(['event_id' => $event->id, 'slot_date' => '2026-08-11']);

        $response = $this->actingAs($user)->getJson("/api/themepark/events/{$event->id}?date=2026-08-10");

        $response->assertOk()->assertJsonCount(1, 'slots');
    }

    public function test_staff_can_create_event(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');

        $response = $this->actingAs($staff)->postJson('/api/themepark/events', [
            'name' => 'Wave Pool',
            'type' => 'beach_event',
            'location' => 'North Shore',
            'duration_minutes' => 30,
            'capacity_per_slot' => 20,
            'price_per_ticket' => 10,
        ]);

        $response->assertCreated()->assertJsonPath('name', 'Wave Pool');
    }

    public function test_visitor_cannot_create_event(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');

        $this->actingAs($visitor)->postJson('/api/themepark/events', [
            'name' => 'Wave Pool',
            'type' => 'beach_event',
            'location' => 'North Shore',
            'duration_minutes' => 30,
            'capacity_per_slot' => 20,
            'price_per_ticket' => 10,
        ])->assertForbidden();
    }

    public function test_staff_can_toggle_event_active(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create(['is_active' => true]);

        $response = $this->actingAs($staff)->patchJson("/api/themepark/events/{$event->id}", [
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('is_active', false);
    }

    public function test_staff_can_upload_an_event_image(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();

        $response = $this->actingAs($staff)->post("/api/themepark/events/{$event->id}", [
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('ride.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('image_url'));
        $this->assertCount(1, $event->fresh()->getMedia('image'));
    }

    public function test_visitor_cannot_upload_an_event_image(): void
    {
        Storage::fake('public');
        $visitor = User::factory()->create()->assignRole('visitor');
        $event = ThemeParkEvent::factory()->create();

        $this->actingAs($visitor)->post("/api/themepark/events/{$event->id}", [
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('ride.jpg'),
        ])->assertForbidden();

        $this->assertCount(0, $event->fresh()->getMedia('image'));
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();

        $this->actingAs($staff)->post("/api/themepark/events/{$event->id}", [
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertInvalid(['image']);

        $this->assertCount(0, $event->fresh()->getMedia('image'));
    }

    public function test_staff_can_remove_an_event_image(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();
        $event->addMedia(UploadedFile::fake()->image('ride.jpg'))->toMediaCollection('image');

        $response = $this->actingAs($staff)->patchJson("/api/themepark/events/{$event->id}", [
            'remove_image' => true,
        ]);

        $response->assertOk()->assertJsonPath('image_url', null);
        $this->assertCount(0, $event->fresh()->getMedia('image'));
    }

    public function test_staff_can_delete_event(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create();

        $this->actingAs($staff)->deleteJson("/api/themepark/events/{$event->id}")
            ->assertNoContent();
    }

    public function test_staff_can_add_a_slot_to_an_event(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $event = ThemeParkEvent::factory()->create(['capacity_per_slot' => 25]);

        $response = $this->actingAs($staff)->postJson("/api/themepark/events/{$event->id}/slots", [
            'slot_date' => '2026-09-01',
            'slot_time' => '14:00',
        ]);

        $response->assertCreated()->assertJsonPath('available_capacity', 25);
    }

    public function test_slots_endpoint_is_public(): void
    {
        // A guest-checkout visitor browses the theme park page (and picks
        // slots for the cart) before an account exists.
        $event = ThemeParkEvent::factory()->create();
        EventSlot::factory()->create(['event_id' => $event->id, 'slot_date' => '2026-08-10']);

        $response = $this->getJson("/api/themepark/events/{$event->id}/slots?date=2026-08-10");

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_visitor_can_book_a_slot(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $slot = EventSlot::factory()->create(['available_capacity' => 10]);

        $response = $this->actingAs($visitor)->postJson('/api/themepark/bookings', [
            'event_slot_id' => $slot->id,
            'ticket_count' => 3,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'confirmed');
        $this->assertEquals(7, $slot->fresh()->available_capacity);
    }

    public function test_booking_fails_when_capacity_exceeded(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $slot = EventSlot::factory()->create(['available_capacity' => 2]);

        $this->actingAs($visitor)->postJson('/api/themepark/bookings', [
            'event_slot_id' => $slot->id,
            'ticket_count' => 3,
        ])->assertUnprocessable();
    }

    // Capacity alone isn't enough of a gate - a cancelled slot keeps whatever
    // capacity it had, so it would otherwise stay purchasable.
    public function test_booking_fails_for_a_cancelled_slot(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $slot = EventSlot::factory()->create([
            'available_capacity' => 10,
            'status' => 'cancelled',
        ]);

        $this->actingAs($visitor)->postJson('/api/themepark/bookings', [
            'event_slot_id' => $slot->id,
            'ticket_count' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('event_bookings', 0);
        $this->assertEquals(10, $slot->fresh()->available_capacity);
    }

    // Visitors have to present something at the gate; the staff scanner reads
    // the trailing digits of this code as the booking id.
    /**
     * The code is prefixed so a scan can be routed to the right lookup, but the
     * rest of it must not be derivable from the row. It used to be
     * sprintf('VFN-E%04d', $id), which made every ticket in the system guessable
     * from any one of them - and the code is the whole credential at the gate.
     */
    public function test_park_bookings_expose_a_reference_code(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $booking = EventBooking::factory()->create(['user_id' => $visitor->id]);

        $response = $this->actingAs($visitor)->getJson('/api/themepark/bookings');

        $response->assertOk();

        $code = $response->json('0.reference_code');
        $this->assertSame($booking->reference_code, $code);
        $this->assertMatchesRegularExpression('/^VFN-E[A-Z0-9]{12}$/', $code);

        // Not derived from the id: the next row's code must not be predictable
        // from this one.
        $next = EventBooking::factory()->create(['user_id' => $visitor->id]);
        $this->assertNotSame(
            substr($code, 5),
            substr($next->reference_code, 5),
            'reference codes must not be sequential'
        );
    }

    public function test_visitor_sees_only_their_own_bookings(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        EventBooking::factory()->create(['user_id' => $visitor->id]);
        EventBooking::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($visitor)->getJson('/api/themepark/bookings');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_visitor_can_cancel_their_booking_and_capacity_is_restored(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        // The event is pinned rather than left to the factory: cancelling clamps
        // the restored capacity to capacity_per_slot, which the factory rolls
        // anywhere from 10 upwards - so a roll below 12 failed this test at
        // random rather than because anything was wrong.
        $event = ThemeParkEvent::factory()->create(['capacity_per_slot' => 20]);
        $slot = EventSlot::factory()->create([
            'event_id' => $event->id,
            'available_capacity' => 10,
        ]);
        $booking = EventBooking::factory()->create([
            'user_id' => $visitor->id,
            'event_slot_id' => $slot->id,
            'ticket_count' => 2,
            'status' => 'confirmed',
        ]);

        $this->actingAs($visitor)->deleteJson("/api/themepark/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertEquals(12, $slot->fresh()->available_capacity);
    }

    public function test_user_cannot_cancel_someone_elses_booking(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        $other = User::factory()->create()->assignRole('visitor');
        $booking = EventBooking::factory()->create(['user_id' => $other->id]);

        $this->actingAs($visitor)->deleteJson("/api/themepark/bookings/{$booking->id}")
            ->assertForbidden();
    }
}

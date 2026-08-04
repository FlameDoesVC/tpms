<?php

namespace Tests\Feature\Security;

use App\Models\EventSlot;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\Hotel;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding F-24: `is_active` was honoured on some public endpoints and
 * ignored on adjacent ones returning the same data. The deliberate `?all=1`
 * gate on the ferry list returned 403 while the same retired boats walked out
 * of GET /api/ferry/schedules.
 */
class PublicExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_hotel_is_not_publicly_listed(): void
    {
        Hotel::factory()->create(['name' => 'Live Hotel', 'is_active' => true]);
        Hotel::factory()->create(['name' => 'Retired Hotel', 'is_active' => false]);

        $names = collect($this->getJson('/api/hotels')->json('data'))->pluck('name');

        $this->assertContains('Live Hotel', $names);
        $this->assertNotContains('Retired Hotel', $names,
            'F-24: a deactivated hotel was publicly listed.');
    }

    public function test_inactive_hotel_is_not_publicly_readable(): void
    {
        $hidden = Hotel::factory()->create(['is_active' => false]);

        $this->getJson("/api/hotels/{$hidden->id}")->assertNotFound();
    }

    public function test_retired_ferry_does_not_leak_through_schedules(): void
    {
        $retired = Ferry::factory()->create(['name' => 'Retired Boat', 'is_active' => false]);
        FerrySchedule::factory()->create([
            'ferry_id' => $retired->id,
            'departure_date' => now()->addDay()->toDateString(),
            'status' => 'scheduled',
        ]);

        $payload = json_encode($this->getJson('/api/ferry/schedules')->json());

        $this->assertStringNotContainsString('Retired Boat', $payload,
            'F-24: a retired ferry leaked through the public schedules endpoint.');
    }

    public function test_inactive_event_is_not_publicly_readable(): void
    {
        $hidden = ThemeParkEvent::factory()->create([
            'name' => 'Unannounced Event',
            'is_active' => false,
        ]);

        $this->getJson("/api/themepark/events/{$hidden->id}")->assertNotFound();
    }

    public function test_staff_slot_calendar_is_not_public(): void
    {
        $hidden = ThemeParkEvent::factory()->create([
            'name' => 'Unannounced Event',
            'is_active' => false,
        ]);
        EventSlot::factory()->create([
            'event_id' => $hidden->id,
            'slot_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertContains($this->getJson('/api/themepark/slots')->status(), [401, 403],
            'F-24: the staff scheduling calendar was readable anonymously.');
    }

    /**
     * The other half of the fix: staff must still be able to see and therefore
     * re-activate a hidden record. Filtering without a management scope makes
     * the is_active toggle one-way.
     */
    public function test_staff_can_still_see_inactive_records(): void
    {
        $manager = User::factory()->create()->assignRole('hotel_manager');
        Hotel::factory()->create(['name' => 'Retired Hotel', 'is_active' => false]);

        $names = collect(
            $this->actingAs($manager)->getJson('/api/hotels?all=1')->json('data')
        )->pluck('name');

        $this->assertContains('Retired Hotel', $names,
            'staff must be able to list inactive hotels to re-activate them.');
    }

    public function test_staff_can_still_see_inactive_events(): void
    {
        $staff = User::factory()->create()->assignRole('themepark_staff');
        ThemeParkEvent::factory()->create(['name' => 'Unannounced Event', 'is_active' => false]);

        $names = collect(
            $this->actingAs($staff)->getJson('/api/themepark/events?all=1')->json()
        )->pluck('name');

        $this->assertContains('Unannounced Event', $names,
            'staff must be able to list inactive events to re-activate them.');
    }

    /** A visitor must not be able to opt into the management view. */
    public function test_visitor_cannot_use_the_management_scope(): void
    {
        $visitor = User::factory()->create()->assignRole('visitor');
        Hotel::factory()->create(['name' => 'Retired Hotel', 'is_active' => false]);

        $names = collect(
            $this->actingAs($visitor)->getJson('/api/hotels?all=1')->json('data')
        )->pluck('name');

        $this->assertNotContains('Retired Hotel', $names,
            'F-24: a visitor escalated to the unfiltered list via ?all=1.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\EventBooking;
use App\Models\EventSlot;
use App\Models\EventSlotTemplate;
use App\Models\Ferry;
use App\Models\FerrySchedule;
use App\Models\FerryScheduleTemplate;
use App\Models\FerryTicket;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\ThemeParkEvent;
use App\Models\User;
use App\Services\EventSlotTemplateGenerator;
use App\Services\ScheduleTemplateGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Shared password for the demo accounts.
     *
     * Deliberately satisfies the policy in config/security.php. It used to be
     * "password", which the application now refuses to accept when a user sets
     * one - so the seeder was handing out a credential its own registration form
     * would reject, and seeding a public environment meant every role was one
     * guess away.
     */
    public const DEMO_PASSWORD = 'lagoon ferry demo 2026';

    /**
     * Seed realistic sample data across every implemented module, for local
     * dev/demo use. Never run this in production - see the deployment checklist
     * in README.md.
     */
    /**
     * A demo account with the shared, policy-compliant password. Set explicitly
     * rather than left to UserFactory, whose default exists for the test suite.
     */
    private function demoUser(string $name, string $email, string $role): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::DEMO_PASSWORD),
        ])->assignRole($role);
    }

    public function run(): void
    {
        $hotelManager = $this->demoUser('Hana Manager', 'hotel_manager@example.com', 'hotel_manager');
        $ferryOperator = $this->demoUser('Fiona Operator', 'ferry_operator@example.com', 'ferry_operator');
        $parkStaff = $this->demoUser('Pete Staff', 'park_staff@example.com', 'themepark_staff');
        $this->demoUser('Ada Admin', 'admin@example.com', 'admin');
        $visitor1 = $this->demoUser('Visitor One', 'visitor1@example.com', 'visitor');
        $visitor2 = $this->demoUser('Visitor Two', 'visitor2@example.com', 'visitor');

        // --- Hotels ---
        $sunsetResort = Hotel::factory()->create([
            'name' => 'Sunset Resort',
            'address' => '1 Beach Road, South Male Atoll',
            'total_rooms' => 12,
        ]);
        $lagoonInn = Hotel::factory()->create([
            'name' => 'Lagoon Inn',
            'address' => '22 Lagoon Drive, North Male Atoll',
            'total_rooms' => 8,
        ]);

        // Multiple rooms per type so a party bigger than one room can still
        // book several units of the same type instead of the hotel appearing sold out.
        $rooms = collect();
        foreach ([$sunsetResort, $lagoonInn] as $hotel) {
            foreach ([['single', 'SG', 80, 1, 2], ['double', 'DB', 120, 2, 3], ['suite', 'ST', 250, 4, 2]] as [$type, $prefix, $price, $maxGuests, $count]) {
                for ($i = 1; $i <= $count; $i++) {
                    $rooms->push(Room::factory()->create([
                        'hotel_id' => $hotel->id,
                        'room_number' => $prefix.$hotel->id.$i,
                        'type' => $type,
                        'price_per_night' => $price,
                        'max_guests' => $maxGuests,
                    ]));
                }
            }
        }

        // Confirmed booking, used to satisfy the ferry ticket eligibility check below.
        $confirmedBooking = Booking::factory()->create([
            'user_id' => $visitor1->id,
            'room_id' => $rooms->first()->id,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(6)->toDateString(),
            'total_price' => $rooms->first()->price_per_night * 3,
            'status' => 'confirmed',
            'guests_count' => 2,
        ]);

        // Unconfirmed booking, so visitor2 demonstrates the "confirmed booking required" ferry gate.
        Booking::factory()->create([
            'user_id' => $visitor2->id,
            'room_id' => $rooms->get(1)->id,
            'status' => 'pending',
        ]);

        // --- Ferries ---
        $islandHopper = Ferry::factory()->create(['name' => 'Island Hopper', 'capacity' => 40, 'price_per_seat' => 20]);
        $seaBreeze = Ferry::factory()->create(['name' => 'Sea Breeze', 'capacity' => 25, 'price_per_seat' => 15]);

        // Daily recurring templates, materialized into FerrySchedule instances by the generator.
        foreach ([$islandHopper, $seaBreeze] as $ferry) {
            FerryScheduleTemplate::factory()->create([
                'ferry_id' => $ferry->id,
                'frequency' => 'daily',
                'departure_time' => '09:00:00',
                'arrival_time' => '10:30:00',
                'available_seats' => $ferry->capacity,
                'starts_on' => now()->addDay()->toDateString(),
                'ends_on' => now()->addDays(5)->toDateString(),
            ]);
        }
        (new ScheduleTemplateGenerator)->generate();

        $ferrySchedule = FerrySchedule::where('ferry_id', $islandHopper->id)->orderBy('departure_date')->firstOrFail();
        $ferrySchedule->decrement('available_seats');
        FerryTicket::factory()->create([
            'user_id' => $visitor1->id,
            'schedule_id' => $ferrySchedule->id,
            'booking_id' => $confirmedBooking->id,
            'seat_number' => 1,
            'status' => 'issued',
            'price' => $ferrySchedule->ferry->price_per_seat,
            'payment_method' => 'online',
        ]);

        // --- Theme park ---
        $ride = ThemeParkEvent::factory()->create([
            'name' => 'Wave Runner',
            'type' => 'ride',
            'location' => 'North Shore',
            'duration_minutes' => 15,
            'capacity_per_slot' => 20,
            'price_per_ticket' => 25,
        ]);
        $show = ThemeParkEvent::factory()->create([
            'name' => 'Sunset Dolphin Show',
            'type' => 'show',
            'location' => 'Marine Theatre',
            'duration_minutes' => 40,
            'capacity_per_slot' => 60,
            'price_per_ticket' => 15,
        ]);
        $beachEvent = ThemeParkEvent::factory()->create([
            'name' => 'Beach Volleyball',
            'type' => 'beach_event',
            'location' => 'Main Beach',
            'duration_minutes' => 60,
            'capacity_per_slot' => 16,
            'price_per_ticket' => 5,
        ]);

        // Daily recurring templates, materialized into EventSlot instances by the generator.
        foreach ([$ride, $show, $beachEvent] as $event) {
            foreach (['10:00:00', '14:00:00'] as $time) {
                EventSlotTemplate::factory()->create([
                    'event_id' => $event->id,
                    'frequency' => 'daily',
                    'slot_time' => $time,
                    'available_capacity' => $event->capacity_per_slot,
                    'starts_on' => now()->toDateString(),
                    'ends_on' => now()->addDays(3)->toDateString(),
                ]);
            }
        }
        (new EventSlotTemplateGenerator)->generate();

        $bookedSlot = EventSlot::where('event_id', $ride->id)->where('slot_time', '10:00:00')->orderBy('slot_date')->firstOrFail();
        $bookedSlot->decrement('available_capacity', 2);
        EventBooking::factory()->create([
            'user_id' => $visitor1->id,
            'event_slot_id' => $bookedSlot->id,
            'ticket_count' => 2,
            'status' => 'confirmed',
        ]);

        $this->command?->info('Demo accounts (password: "'.self::DEMO_PASSWORD.'"):');
        $this->command?->table(['Role', 'Email'], [
            ['hotel_manager', $hotelManager->email],
            ['ferry_operator', $ferryOperator->email],
            ['themepark_staff', $parkStaff->email],
            ['admin', 'admin@example.com'],
            ['visitor', $visitor1->email],
            ['visitor', $visitor2->email],
        ]);
    }
}

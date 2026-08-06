<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Promote the runtime "room type" grouping into real rows.
     *
     * Room types used to be synthesised on every request by grouping rooms on
     * type|price_per_night|max_guests. This walks the same grouping once and
     * writes it to room_types, then points each room at its group.
     *
     * Query-builder only, deliberately: the Eloquent models describe the schema
     * this migration is on its way to, not the one it is reading.
     */
    public function up(): void
    {
        $groups = DB::table('rooms')
            ->select('hotel_id', 'type', 'price_per_night', 'max_guests')
            ->groupBy('hotel_id', 'type', 'price_per_night', 'max_guests')
            ->orderBy('hotel_id')
            ->orderBy('price_per_night')
            ->get();

        // Groups include unavailable rooms on purpose - every room needs a type
        // before the next migration makes the column required.
        $usedNames = [];
        $now = now();

        foreach ($groups as $group) {
            $name = ucfirst((string) $group->type);

            // The (hotel_id, name) unique index does not survive a hotel that
            // sells the same room type at two price points.
            if (isset($usedNames[$group->hotel_id][$name])) {
                $name = $name.' ('.rtrim(rtrim(number_format((float) $group->price_per_night, 2, '.', ''), '0'), '.').')';
            }
            $usedNames[$group->hotel_id][$name] = true;

            $roomTypeId = DB::table('room_types')->insertGetId([
                'hotel_id' => $group->hotel_id,
                'name' => $name,
                'description' => null,
                'price_per_night' => $group->price_per_night,
                'max_guests' => $group->max_guests,
                'amenities' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('rooms')
                ->where('hotel_id', $group->hotel_id)
                ->where('type', $group->type)
                ->where('price_per_night', $group->price_per_night)
                ->where('max_guests', $group->max_guests)
                ->update(['room_type_id' => $roomTypeId]);
        }
    }

    public function down(): void
    {
        DB::table('rooms')->update(['room_type_id' => null]);
        DB::table('room_types')->delete();
    }
};

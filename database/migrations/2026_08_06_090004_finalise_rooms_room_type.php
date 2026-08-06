<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every room is typed by now, so the denormalised columns can go and the
     * link to room_types becomes required.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('room_type_id')->nullable(false)->change();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['type', 'price_per_night', 'max_guests']);
        });
    }

    /**
     * Restores the columns and copies the values back off each room's type, so
     * a rollback leaves rooms usable rather than merely well-shaped. `type` comes
     * back as a plain string: the original enum cannot be reconstructed from a
     * free-text room type name.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('type')->nullable()->after('room_number');
            $table->decimal('price_per_night', 8, 2)->nullable()->after('type');
            $table->unsignedInteger('max_guests')->nullable()->after('price_per_night');
        });

        // Correlated subqueries rather than an UPDATE ... JOIN, which SQLite lacks.
        DB::statement('UPDATE rooms SET
            type = (SELECT LOWER(rt.name) FROM room_types rt WHERE rt.id = rooms.room_type_id),
            price_per_night = (SELECT rt.price_per_night FROM room_types rt WHERE rt.id = rooms.room_type_id),
            max_guests = (SELECT rt.max_guests FROM room_types rt WHERE rt.id = rooms.room_type_id)
            WHERE room_type_id IS NOT NULL');

        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('room_type_id')->nullable()->change();
        });
    }
};

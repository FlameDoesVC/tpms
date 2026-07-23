<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Null on the first room of a multi-room purchase (the anchor);
            // every sibling room created in the same purchase points its
            // group_booking_id at that anchor's id, so the party's total
            // guest capacity can be summed across the whole group.
            $table->foreignId('group_booking_id')->nullable()->after('room_id')
                ->constrained('bookings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['group_booking_id']);
            $table->dropColumn('group_booking_id');
        });
    }
};

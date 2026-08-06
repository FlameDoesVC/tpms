<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable for now - the next migration backfills it from the existing
     * rooms, and the one after that makes it required.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('room_type_id')
                ->nullable()
                ->after('hotel_id')
                ->constrained('room_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_type_id');
        });
    }
};

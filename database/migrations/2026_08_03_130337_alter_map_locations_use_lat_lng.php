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
        Schema::table('map_locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->default(2.171568)->after('type');
            $table->decimal('longitude', 10, 7)->default(73.079713)->after('latitude');
            $table->dropColumn(['position_top', 'position_left']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_locations', function (Blueprint $table) {
            $table->decimal('position_top', 5, 2)->default(50)->after('type');
            $table->decimal('position_left', 5, 2)->default(50)->after('position_top');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};

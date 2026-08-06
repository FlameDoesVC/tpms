<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_park_events', function (Blueprint $table) {
            $table->json('highlights')->nullable()->after('description');
            $table->unsignedSmallInteger('min_age')->nullable()->after('duration_minutes');
            $table->unsignedSmallInteger('min_height_cm')->nullable()->after('min_age');
        });
    }

    public function down(): void
    {
        Schema::table('theme_park_events', function (Blueprint $table) {
            $table->dropColumn(['highlights', 'min_age', 'min_height_cm']);
        });
    }
};

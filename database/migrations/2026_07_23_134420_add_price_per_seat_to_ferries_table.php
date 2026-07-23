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
        Schema::table('ferries', function (Blueprint $table) {
            $table->decimal('price_per_seat', 8, 2)->default(15)->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ferries', function (Blueprint $table) {
            $table->dropColumn('price_per_seat');
        });
    }
};

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
        Schema::create('event_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('theme_park_events')->cascadeOnDelete();
            $table->date('slot_date');
            $table->time('slot_time');
            $table->unsignedInteger('available_capacity');
            $table->enum('status', ['scheduled', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->index(['event_id', 'slot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_slots');
    }
};

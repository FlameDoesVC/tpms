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
        Schema::create('event_bookings', function (Blueprint $table) {
            $table->id();
            // Nullable: walk-in tickets sold on-site (row 43/53) have no visitor account.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('visitor_name')->nullable();
            $table->foreignId('event_slot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ticket_count');
            // 'used' added beyond Tasks.csv row 41's confirmed/cancelled — needed for on-site
            // ticket validation (row 43/52) to distinguish a valid ticket from one already scanned.
            $table->enum('status', ['confirmed', 'cancelled', 'used'])->default('confirmed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_bookings');
    }
};

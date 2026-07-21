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
        Schema::create('theme_park_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['ride', 'show', 'beach_event']);
            $table->string('location');
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('capacity_per_slot');
            // Not in Tasks.csv row 41 spec — added so daily sales reports (row 43) have a revenue figure to compute.
            $table->decimal('price_per_ticket', 8, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_park_events');
    }
};

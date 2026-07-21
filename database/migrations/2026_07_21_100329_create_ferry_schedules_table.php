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
        Schema::create('ferry_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ferry_id')->constrained()->cascadeOnDelete();
            $table->date('departure_date');
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->unsignedInteger('available_seats');
            $table->enum('status', ['scheduled', 'departed', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->index(['ferry_id', 'departure_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ferry_schedules');
    }
};

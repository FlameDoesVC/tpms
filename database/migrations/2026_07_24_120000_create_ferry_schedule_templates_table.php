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
        Schema::create('ferry_schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ferry_id')->constrained()->cascadeOnDelete();
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->unsignedInteger('available_seats');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ferry_schedule_templates');
    }
};

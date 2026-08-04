<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment was previously not modelled at all: a booking's `confirmed` status
     * was the only record that money had changed hands, and the customer could
     * write that status themselves. This table makes settlement an explicit,
     * server-created fact with an amount, an actor and an audit trail.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Bookings today; ferry tickets and event bookings can settle the
            // same way without another table.
            $table->morphs('payable');
            $table->decimal('amount', 10, 2);
            $table->string('method');                   // card | cash
            $table->string('status')->default('captured');
            // Server-generated. Never accepted from the client.
            $table->string('reference')->unique();
            // Who took the money - the visitor for an online payment, the staff
            // member for cash at the desk.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

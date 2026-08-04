<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who admitted a passenger, and who cancelled a booking.
     *
     * None of this was recorded. Nothing said which operator marked a ticket used
     * or when, so an over-boarded sailing, a disputed refund, or a staff member
     * validating tickets they should not have were all equally invisible - and so
     * was every exploit found in the security audit.
     */
    public function up(): void
    {
        Schema::table('ferry_tickets', function (Blueprint $table) {
            $table->foreignId('validated_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->foreignId('cancelled_by')->nullable()->after('validated_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });

        Schema::table('event_bookings', function (Blueprint $table) {
            $table->foreignId('validated_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->foreignId('cancelled_by')->nullable()->after('validated_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('ferry_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['validated_at', 'cancelled_at']);
        });

        Schema::table('event_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['validated_at', 'cancelled_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
        });
    }
};

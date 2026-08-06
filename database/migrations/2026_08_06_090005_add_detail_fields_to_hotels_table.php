<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->json('facilities')->nullable()->after('address');
            $table->time('check_in_time')->nullable()->after('facilities');
            $table->time('check_out_time')->nullable()->after('check_in_time');
            $table->string('phone')->nullable()->after('check_out_time');
            $table->string('email')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'facilities',
                'check_in_time',
                'check_out_time',
                'phone',
                'email',
                'website',
            ]);
        });
    }
};

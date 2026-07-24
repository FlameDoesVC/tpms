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
        Schema::table('ferry_schedules', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('ferry_id')
                ->constrained('ferry_schedule_templates')->nullOnDelete();
            $table->boolean('is_overridden')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ferry_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('is_overridden');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Reference codes were derived from the row's auto-increment id
     * (sprintf('VFN-T%04d', $id)), and that string is the entire credential
     * presented at the gate: the scanners read the digits straight back into a
     * primary key. Anyone could render a QR for VFN-T0007 and board on someone
     * else's ticket, or read a stranger's whole party off a guessed VFN-B0001.
     *
     * These columns replace the derivation with a stored, unguessable value. The
     * prefix letter stays because it routes the scan to the right lookup - it is
     * not a secret and does not need to be.
     */
    private const TABLES = [
        'bookings' => 'VFN-B',
        'ferry_tickets' => 'VFN-T',
        'event_bookings' => 'VFN-E',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $prefix) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('reference_code', 32)->nullable()->unique()->after('id');
            });

            $this->backfill($table, $prefix);
        }
    }

    /**
     * Deliberately uses the query builder, not Eloquent: at this point the models
     * still define a reference_code accessor, which would shadow the column and
     * write the old derived value straight back.
     */
    private function backfill(string $table, string $prefix): void
    {
        DB::table($table)
            ->select('id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($table, $prefix) {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['reference_code' => $prefix.Str::upper(Str::random(12))]);
                }
            });
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // Passing the column lets Laravel derive the conventional index
                // name; naming it by hand produced a doubled table prefix.
                $blueprint->dropUnique(['reference_code']);
                $blueprint->dropColumn('reference_code');
            });
        }
    }
};

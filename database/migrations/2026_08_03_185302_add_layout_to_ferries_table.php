<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The deck plan: which cells of the grid are seats, and where people board.
     *
     * Nullable on purpose. Every ferry that predates this column keeps a null
     * layout and is rendered from the generated four-plus-aisle-plus-four
     * fallback in Ferry::deck(), which reproduces the numbering the app already
     * uses - so existing tickets keep pointing at the seat they were sold. A
     * layout is only written once someone actually edits one.
     */
    public function up(): void
    {
        Schema::table('ferries', function (Blueprint $table) {
            $table->json('layout')->nullable()->after('price_per_seat');
        });
    }

    public function down(): void
    {
        Schema::table('ferries', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};

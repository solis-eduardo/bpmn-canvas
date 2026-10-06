<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Holds the Lock holder's unsaved Editor content, refreshed by the Heartbeat,
 * so it can be saved as a provisional Version if the Lock expires (ADR-0003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->longText('lock_draft')->nullable()->after('last_heartbeat_at');
        });

        // `locks:expire` scans for held Locks every minute. Held Locks are a
        // small fraction of the rows, so a partial index keeps that an index
        // probe that usually returns nothing instead of a table scan.
        DB::statement('create index diagrams_held_locks_index on diagrams (last_heartbeat_at) where locked_by is not null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists diagrams_held_locks_index');

        Schema::table('diagrams', function (Blueprint $table) {
            $table->dropColumn('lock_draft');
        });
    }
};

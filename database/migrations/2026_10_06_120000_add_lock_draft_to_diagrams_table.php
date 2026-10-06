<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    public function down(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->dropColumn('lock_draft');
        });
    }
};

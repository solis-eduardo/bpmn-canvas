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
        Schema::table('diagramas', function (Blueprint $table) {
            $table->foreignId('lock_requested_by')->nullable()->after('last_heartbeat_at')->constrained('users')->nullOnDelete();
            $table->timestamp('lock_requested_at')->nullable()->after('lock_requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagramas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lock_requested_by');
            $table->dropColumn(['lock_requested_at']);
        });
    }
};

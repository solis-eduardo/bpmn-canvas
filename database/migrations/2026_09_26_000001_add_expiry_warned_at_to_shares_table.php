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
        // Null until `shares:warn-expiring` notifies the Diagram Owner that
        // this Share is about to expire. Once set, the same Share is never
        // warned about again (see Share::isDueForExpiryWarning()) — this
        // column, not the notifications table, is the idempotence guard,
        // since a Share's warning is a one-time fact about the Share itself.
        Schema::table('shares', function (Blueprint $table) {
            $table->timestamp('expiry_warned_at')->nullable()->after('revoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropColumn('expiry_warned_at');
        });
    }
};

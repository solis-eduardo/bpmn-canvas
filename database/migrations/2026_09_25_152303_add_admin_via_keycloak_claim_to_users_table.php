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
        Schema::table('users', function (Blueprint $table) {
            // Tracks whether the current is_admin=true came from the Keycloak
            // role/claim rather than a manual grant by another Administrator —
            // per ADR 0005, only a manual origin can be turned off, and a claim
            // origin is displayed but not revocable from the User management screen.
            $table->boolean('is_admin_via_keycloak_claim')->default(false)->after('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin_via_keycloak_claim');
        });
    }
};

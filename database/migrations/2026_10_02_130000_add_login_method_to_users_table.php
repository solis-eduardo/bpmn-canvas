<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records each User's single login method explicitly (ADR-0015). Accounts
 * linked to a Keycloak identity become `keycloak`, all others `local`.
 * `password` becomes nullable so a `keycloak` User can exist without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login_method')->default('local')->after('password');
            $table->string('password')->nullable()->change();
        });

        DB::table('users')->whereNotNull('keycloak_id')->update(['login_method' => 'keycloak']);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('password')->update(['password' => '']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->dropColumn('login_method');
        });
    }
};

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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('keycloak_group')->nullable();
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->string('origin')->default('manual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            $table->dropColumn('origin');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('keycloak_group');
        });
    }
};

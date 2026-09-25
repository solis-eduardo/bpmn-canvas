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
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The Workspace Owner (CONTEXT.md, "Workspace Owner"; ADR-0009):
            // starts as the creator, transferable later (issue #27+). Kept
            // as its own column, mirroring Diagram::owner_id, so "who
            // manages this Workspace" stays a single, cheap lookup instead
            // of a role flag inside workspace_members.
            $table->foreignId('owner_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};

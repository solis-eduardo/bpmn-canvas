<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a Diagram an optional Workspace reference (ADR-0006, CONTEXT.md
 * "Workspace": "A Diagram belongs to at most one Workspace"). Putting a
 * Diagram into (or out of) a Workspace, and the visibility/edit
 * consequences of doing so, are a later ticket (#27) — this migration only
 * adds the column, since issue #29's "a Workspace holding Diagrams can't be
 * deleted" rule needs somewhere to check.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('owner_id')->constrained('workspaces')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });
    }
};

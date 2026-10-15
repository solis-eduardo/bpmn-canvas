<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a Diagram as a Template (CONTEXT.md, "Template"; issue #255): a
 * Diagram whose content new Diagrams can start from. Whether it is global or
 * per Workspace follows from the Diagram's own Workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->boolean('is_template')->default(false)->after('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->dropColumn('is_template');
        });
    }
};

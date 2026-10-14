<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a Diagram removes its Shares, so none of its share links keep
 * working. The original `shares.diagram_id` foreign key was
 * created without a delete rule (NO ACTION), so deleting a Diagram that had
 * ever been shared failed with a database constraint violation. Every other
 * table referencing `diagrams` already cascades.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropForeign(['diagram_id']);
            $table->foreign('diagram_id')->references('id')->on('diagrams')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropForeign(['diagram_id']);
            $table->foreign('diagram_id')->references('id')->on('diagrams');
        });
    }
};

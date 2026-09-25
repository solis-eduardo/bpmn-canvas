<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduces the Diagram Owner (ADR-0006, CONTEXT.md "Diagram Owner"): the
 * User currently responsible for a Diagram, distinct from its (fixed)
 * Author. Existing Diagrams are backfilled with the Author as Diagram
 * Owner; new Diagrams and Forks start with their creator as both (see
 * DiagramController@store/@fork).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('author_id')->constrained('users');
        });

        DB::table('diagrams')->update(['owner_id' => DB::raw('author_id')]);

        Schema::table('diagrams', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};

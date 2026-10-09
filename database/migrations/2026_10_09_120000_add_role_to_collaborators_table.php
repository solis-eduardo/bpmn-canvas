<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Collaborator is either a plain `collaborator` or a `manager` (Diagram
 * Manager, ADR-0016). Every existing row stays a plain Collaborator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborators', function (Blueprint $table) {
            $table->string('role')->default('collaborator')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('collaborators', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

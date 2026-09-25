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
        // Stores only the Versions that were explicitly renamed (see
        // ADR-0004: no Git tags are used to keep the name, since it must be
        // reachable/queryable outside of `git`, and Git has no concept of
        // renaming a commit in place). A Version without a row here uses the
        // default "Version N" name, N being derived from its chronological
        // position in the Diagram's Git history (see VersionRepository).
        Schema::create('diagram_version_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->string('version_id');
            $table->string('name');
            $table->timestamps();

            $table->unique(['diagram_id', 'version_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagram_version_names');
    }
};

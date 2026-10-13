<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Version history for the database adapter (VERSION_STORE=database, ADR-0018):
     * one row per Version, holding the full content it recorded. The `id`
     * sequence gives chronological order, which numbers the Versions.
     */
    public function up(): void
    {
        Schema::create('diagram_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->string('version_id', 40)->unique();
            $table->longText('content');
            $table->string('author_name');
            $table->string('message');
            $table->timestamp('saved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_versions');
    }
};

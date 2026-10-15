<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comment Threads and their Comments (CONTEXT.md; ADR-0022). A Thread
     * belongs to a Diagram and optionally to one BPMN element id, stored as
     * plain text: whether that element still exists is decided on read.
     */
    public function up(): void
    {
        Schema::create('comment_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->string('element_id')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('diagram_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->timestamps();

            $table->index('comment_thread_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('comment_threads');
    }
};

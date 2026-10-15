<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The append-only activity log (CONTEXT.md, "Activity Log"; ADR-0022).
 * Names and titles are copied into the row and the Diagram/Workspace/User
 * columns carry no foreign key: an entry must outlive the thing it describes
 * and stay readable after a rename or a deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('action', 64)->index();
            $table->unsignedBigInteger('diagram_id')->nullable()->index();
            $table->string('diagram_title')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('workspace_name')->nullable();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

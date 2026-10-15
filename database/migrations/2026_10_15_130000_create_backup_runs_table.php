<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->boolean('succeeded');
            $table->string('artifact')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at');

            $table->index(['kind', 'succeeded', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};

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
        Schema::create('diagrama_slugs_antigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagrama_id')->constrained('diagramas')->cascadeOnDelete();
            $table->string('slug_antigo')->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagrama_slugs_antigos');
    }
};

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
        Schema::create('diagrama_colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagrama_id')->constrained('diagramas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['diagrama_id', 'usuario_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagrama_colaboradores');
    }
};

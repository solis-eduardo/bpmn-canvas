<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('diagram_collaborators', 'collaborators');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('collaborators', 'diagram_collaborators');
    }
};

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
        Schema::rename('workspace_members', 'members');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('members', 'workspace_members');
    }
};

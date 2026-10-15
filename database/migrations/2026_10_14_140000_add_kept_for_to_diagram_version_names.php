<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whose unsaved content a provisional Version kept (the Lock holder's), so
     * a save from that User's still-open Editor can be told apart from a save
     * by someone else (issue #243). Null for Versions made before this column
     * and for Versions that are not provisional.
     */
    public function up(): void
    {
        Schema::table('diagram_version_names', function (Blueprint $table) {
            $table->foreignId('kept_for_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('diagram_version_names', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kept_for_id');
        });
    }
};

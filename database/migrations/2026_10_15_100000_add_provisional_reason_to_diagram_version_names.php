<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a provisional Version was kept ('expired' or 'released', see
     * ProvisionalReason), so its name says what actually happened to the
     * Lock (issue #263). Null for Versions made before this column, which
     * were all named for expiry, and for Versions that are not provisional.
     */
    public function up(): void
    {
        Schema::table('diagram_version_names', function (Blueprint $table) {
            $table->string('provisional_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('diagram_version_names', function (Blueprint $table) {
            $table->dropColumn('provisional_reason');
        });
    }
};

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
        // Null means the Share follows the Diagram's latest Version
        // (default, current behaviour). When set, it's the opaque Version
        // identifier (the same "id" VersionController/VersionRepository
        // already expose — see ADR-0004) the Share is pinned to, resolved
        // through VersionRepository rather than duplicating Version lookup
        // logic here.
        Schema::table('shares', function (Blueprint $table) {
            $table->string('pinned_version_id')->nullable()->after('allow_download');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropColumn('pinned_version_id');
        });
    }
};

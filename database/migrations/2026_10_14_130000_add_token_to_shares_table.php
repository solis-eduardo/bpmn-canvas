<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Share links used to be signed URLs whose signature was an HMAC with
 * APP_KEY, so rotating the key broke every link already handed out. Each
 * Share now carries its own unguessable token, which is what its public URL
 * is built from. Existing Shares are backfilled with a token so their rows
 * stay valid; their old signed URLs stop working, as the id-based route is
 * gone.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->after('id');
        });

        DB::table('shares')->whereNull('token')->orderBy('id')->chunkById(100, function ($shares) {
            foreach ($shares as $share) {
                DB::table('shares')->where('id', $share->id)->update(['token' => Str::random(48)]);
            }
        });

        Schema::table('shares', function (Blueprint $table) {
            $table->string('token', 64)->nullable(false)->change();
            $table->unique('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provisional Versions become an explicit state instead of being told
     * apart by a localized name, and a Version can be hidden from the
     * history (soft delete: the Git commit and the numbering stay, see
     * ADR-0004). A provisional Version no longer needs a stored name, so
     * `name` becomes nullable (null falls back to the default name or, for
     * a provisional Version, to the localized provisional label).
     */
    public function up(): void
    {
        Schema::table('diagram_version_names', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->boolean('is_provisional')->default(false);
            $table->boolean('is_hidden')->default(false);
        });

        // Rows written by LockExpiryService before this migration carry the
        // provisional label as their name, in the locale active at the time.
        $labels = collect(['en', 'pt_BR', 'es'])
            ->map(fn (string $locale) => __('messages.provisional_version', [], $locale))
            ->all();

        DB::table('diagram_version_names')
            ->whereIn('name', $labels)
            ->update(['is_provisional' => true, 'name' => null]);
    }

    public function down(): void
    {
        DB::table('diagram_version_names')->whereNull('name')->delete();

        Schema::table('diagram_version_names', function (Blueprint $table) {
            $table->dropColumn(['is_provisional', 'is_hidden']);
            $table->string('name')->nullable(false)->change();
        });
    }
};

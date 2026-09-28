<?php

use App\Support\BpmnSearchableText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Introduces content search by BPMN element names (task, event, gateway,
 * lane and pool names — CONTEXT.md; issue #41). searchable_text is a plain
 * column populated on every save/restore (see VersionRepository::save());
 * existing Diagrams are backfilled here from their current content on disk.
 *
 * On Postgres, an expression GIN index backs full-text search over that
 * column (no external search engine — the roadmap spec is explicit about
 * that), queried via to_tsvector()/plainto_tsquery() in
 * Diagram::scopeSearch(). Skipped on other drivers: the test suite is
 * forced onto sqlite (phpunit.xml), which has no to_tsvector()/GIN support,
 * so scopeSearch() falls back to a portable LIKE match there — same
 * content contract, just without Postgres's index.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('diagrams', function (Blueprint $table) {
            $table->text('searchable_text')->nullable()->after('disk_path');
        });

        DB::table('diagrams')->select('id', 'disk_path')->orderBy('id')->each(function (object $diagram) {
            $content = Storage::disk('diagrams')->get($diagram->disk_path);

            DB::table('diagrams')->where('id', $diagram->id)->update([
                'searchable_text' => is_string($content) ? BpmnSearchableText::extract($content) : '',
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE INDEX diagrams_searchable_text_fts ON diagrams USING GIN (to_tsvector('simple', coalesce(searchable_text, '')))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS diagrams_searchable_text_fts');
        }

        Schema::table('diagrams', function (Blueprint $table) {
            $table->dropColumn('searchable_text');
        });
    }
};

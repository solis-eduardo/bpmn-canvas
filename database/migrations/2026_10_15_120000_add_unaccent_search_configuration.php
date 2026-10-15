<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accent-insensitive search (issue #247). Enables the unaccent extension and
 * builds a 'simple_unaccent' text-search configuration ('simple' plus the
 * unaccent dictionary, so no stemming or stop words), then rebuilds the GIN
 * index on diagrams.searchable_text over it. Diagram::scopeSearch() queries
 * with the same configuration so the index is used.
 *
 * Postgres only: sqlite (the test suite) has neither, and Diagram registers
 * an unaccent() function there instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS simple_unaccent');
        DB::statement('CREATE TEXT SEARCH CONFIGURATION simple_unaccent (COPY = simple)');
        DB::statement(
            'ALTER TEXT SEARCH CONFIGURATION simple_unaccent ALTER MAPPING FOR '
            .'asciiword, word, numword, asciihword, hword, numhword, hword_asciipart, hword_part, hword_numpart '
            .'WITH unaccent, simple'
        );

        DB::statement('DROP INDEX IF EXISTS diagrams_searchable_text_fts');
        DB::statement(
            "CREATE INDEX diagrams_searchable_text_fts ON diagrams USING GIN (to_tsvector('simple_unaccent', coalesce(searchable_text, '')))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS diagrams_searchable_text_fts');
        DB::statement(
            "CREATE INDEX diagrams_searchable_text_fts ON diagrams USING GIN (to_tsvector('simple', coalesce(searchable_text, '')))"
        );
        DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS simple_unaccent');
        // The unaccent extension is left installed: other objects may use it.
    }
};

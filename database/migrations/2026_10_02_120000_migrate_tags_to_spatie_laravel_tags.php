<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Moves the hand-rolled `tags` / `diagram_tag` tables onto the schema
 * spatie/laravel-tags expects (`tags` with translatable name/slug, plus the
 * polymorphic `taggables` pivot), carrying every existing Tag and its
 * Diagram associations over (issue #123, ADR-0014).
 */
return new class extends Migration
{
    private const LOCALE = 'en';

    // Frozen on purpose: a migration must not follow a later rename of the model.
    private const DIAGRAM_MORPH_TYPE = 'App\\Models\\Diagram';

    public function up(): void
    {
        // Read into memory and drop first: renaming a table in Postgres keeps
        // its primary key, unique index and sequence names, which would clash
        // with the new `tags` table's.
        $legacyTags = DB::table('tags')->orderBy('id')->get();
        $legacyLinks = DB::table('diagram_tag')->orderBy('id')->get();

        Schema::drop('diagram_tag');
        Schema::drop('tags');

        Schema::create('tags', function (Blueprint $table) {
            $table->id();

            $table->json('name');
            $table->json('slug');
            $table->string('type')->nullable();
            $table->integer('order_column')->nullable();

            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->morphs('taggable');

            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });

        $idMap = [];

        foreach ($legacyTags as $legacy) {
            $idMap[$legacy->id] = DB::table('tags')->insertGetId([
                'name' => json_encode([self::LOCALE => $legacy->name]),
                'slug' => json_encode([self::LOCALE => Str::slug($legacy->name)]),
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->created_at,
            ]);
        }

        foreach ($legacyLinks as $link) {
            DB::table('taggables')->insert([
                'tag_id' => $idMap[$link->tag_id],
                'taggable_type' => self::DIAGRAM_MORPH_TYPE,
                'taggable_id' => $link->diagram_id,
            ]);
        }
    }

    public function down(): void
    {
        $tags = DB::table('tags')->orderBy('id')->get();
        $links = DB::table('taggables')->where('taggable_type', self::DIAGRAM_MORPH_TYPE)->get();

        Schema::drop('taggables');
        Schema::drop('tags');

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('diagram_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained('diagrams')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['diagram_id', 'tag_id']);
        });

        $idMap = [];

        foreach ($tags as $tag) {
            $names = json_decode($tag->name, true);
            $name = $names[self::LOCALE] ?? (string) Arr::first($names);

            $idMap[$tag->id] = DB::table('tags')->where('name', $name)->value('id')
                ?? DB::table('tags')->insertGetId(['name' => $name, 'created_at' => $tag->created_at]);
        }

        foreach ($links as $link) {
            DB::table('diagram_tag')->insertOrIgnore([
                'diagram_id' => $link->taggable_id,
                'tag_id' => $idMap[$link->tag_id],
            ]);
        }
    }
};

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

    public function up(): void
    {
        Schema::rename('tags', 'legacy_tags');

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

        DB::table('legacy_tags')->orderBy('id')->each(function ($legacy) use (&$idMap) {
            $idMap[$legacy->id] = DB::table('tags')->insertGetId([
                'name' => json_encode([self::LOCALE => $legacy->name]),
                'slug' => json_encode([self::LOCALE => Str::slug($legacy->name)]),
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->created_at,
            ]);
        });

        DB::table('diagram_tag')->orderBy('id')->each(function ($row) use ($idMap) {
            DB::table('taggables')->insert([
                'tag_id' => $idMap[$row->tag_id],
                'taggable_type' => 'App\\Models\\Diagram',
                'taggable_id' => $row->diagram_id,
            ]);
        });

        Schema::drop('diagram_tag');
        Schema::drop('legacy_tags');
    }

    public function down(): void
    {
        Schema::create('legacy_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('diagram_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained('diagrams')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('legacy_tags')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['diagram_id', 'tag_id']);
        });

        $idMap = [];

        DB::table('tags')->orderBy('id')->each(function ($tag) use (&$idMap) {
            $name = json_decode($tag->name, true)[self::LOCALE] ?? (string) Arr::first(json_decode($tag->name, true));
            $existing = DB::table('legacy_tags')->where('name', $name)->value('id');
            $idMap[$tag->id] = $existing ?? DB::table('legacy_tags')->insertGetId([
                'name' => $name,
                'created_at' => $tag->created_at,
            ]);
        });

        DB::table('taggables')->where('taggable_type', 'App\\Models\\Diagram')->orderBy('tag_id')->orderBy('taggable_id')->each(function ($row) use ($idMap) {
            DB::table('diagram_tag')->insertOrIgnore([
                'diagram_id' => $row->taggable_id,
                'tag_id' => $idMap[$row->tag_id],
            ]);
        });

        Schema::drop('taggables');
        Schema::drop('tags');
        Schema::rename('legacy_tags', 'tags');
    }
};

<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Existing Tags and their Diagram associations survive the move onto
 * spatie/laravel-tags (issue #123, ADR-0014).
 */
class TagsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_tags_and_their_diagram_associations_are_migrated(): void
    {
        Storage::fake('diagrams');
        $first = Diagram::factory()->create();
        $second = Diagram::factory()->create();

        $migration = require database_path('migrations/2026_10_02_120000_migrate_tags_to_spatie_laravel_tags.php');
        $migration->down();

        $payroll = DB::table('tags')->insertGetId(['name' => 'Payroll Ops', 'created_at' => now()]);
        $unused = DB::table('tags')->insertGetId(['name' => 'Unused', 'created_at' => now()]);
        DB::table('diagram_tag')->insert([
            ['diagram_id' => $first->id, 'tag_id' => $payroll],
            ['diagram_id' => $second->id, 'tag_id' => $payroll],
        ]);

        $migration->up();

        $this->assertSame(2, Tag::count());
        $this->assertNotNull(Tag::findFromString('Unused'));
        $this->assertSame(['Payroll Ops'], $first->fresh()->tags->map->name->all());
        $this->assertSame(['Payroll Ops'], $second->fresh()->tags->map->name->all());
        $this->assertSame(0, Diagram::withAnyTags(['Unused'])->count());
        $this->assertSame(2, Diagram::withAnyTags(['Payroll Ops'])->count());
        $this->assertFalse(Schema::hasTable('diagram_tag'));
        $this->assertFalse(Schema::hasTable('legacy_tags'));
    }
}

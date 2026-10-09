<?php

namespace Tests\Feature;

use App\Models\Diagram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Version names written by the Lock expiry before provisional became an
 * explicit state are recognised by their localized label (issue #199).
 */
class ProvisionalVersionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_provisional_names_become_the_flag_in_every_locale(): void
    {
        Storage::fake('diagrams');
        $diagram = Diagram::factory()->create();

        $migration = require database_path('migrations/2026_10_09_120000_add_provisional_and_hidden_to_diagram_version_names.php');
        $migration->down();

        $names = [
            'v-en' => __('messages.provisional_version', [], 'en'),
            'v-pt' => __('messages.provisional_version', [], 'pt_BR'),
            'v-es' => __('messages.provisional_version', [], 'es'),
            'v-milestone' => 'Approved by the board',
        ];

        foreach ($names as $versionId => $name) {
            DB::table('diagram_version_names')->insert([
                'diagram_id' => $diagram->id,
                'version_id' => $versionId,
                'name' => $name,
            ]);
        }

        $migration->up();

        $rows = DB::table('diagram_version_names')->get()->keyBy('version_id');

        foreach (['v-en', 'v-pt', 'v-es'] as $versionId) {
            $this->assertTrue((bool) $rows[$versionId]->is_provisional);
            $this->assertNull($rows[$versionId]->name);
        }

        $this->assertFalse((bool) $rows['v-milestone']->is_provisional);
        $this->assertSame('Approved by the board', $rows['v-milestone']->name);
        $this->assertFalse((bool) $rows['v-milestone']->is_hidden);
    }
}

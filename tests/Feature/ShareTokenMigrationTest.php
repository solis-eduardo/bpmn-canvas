<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shares created before public links moved to per-Share tokens get one
 * backfilled, so their rows stay valid once the column is required (issue #238).
 */
class ShareTokenMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_shares_are_backfilled_with_unique_tokens(): void
    {
        $author = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $migration = require database_path('migrations/2026_10_14_130000_add_token_to_shares_table.php');
        $migration->down();

        foreach (range(1, 150) as $i) {
            DB::table('shares')->insert([
                'diagram_id' => $diagram->id,
                'expires_at' => now()->addDays(7),
                'allow_download' => false,
                'created_by' => $author->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration->up();

        $tokens = DB::table('shares')->pluck('token');

        $this->assertCount(150, $tokens);
        $this->assertTrue($tokens->every(fn ($token) => is_string($token) && strlen($token) === 48));
        $this->assertCount(150, $tokens->unique());
    }
}

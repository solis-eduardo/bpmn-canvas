<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Existing accounts get a recorded login method from their Keycloak link
 * (issue #154, ADR-0015).
 */
class LoginMethodMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_accounts_are_migrated_by_their_keycloak_link(): void
    {
        $linked = User::factory()->create(['keycloak_id' => 'kc-1']);
        $plain = User::factory()->create(['keycloak_id' => null]);

        $migration = require database_path('migrations/2026_10_02_130000_add_login_method_to_users_table.php');
        $migration->down();

        $migration->up();

        $this->assertSame('keycloak', DB::table('users')->where('id', $linked->id)->value('login_method'));
        $this->assertSame('local', DB::table('users')->where('id', $plain->id)->value('login_method'));
    }
}

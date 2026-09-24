<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_an_admin_user_from_env(): void
    {
        config(['bpmn.admin_email' => 'admin@example.com']);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);
    }

    public function test_seeder_is_idempotent(): void
    {
        config(['bpmn.admin_email' => 'admin@example.com']);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }

    public function test_seeder_creates_an_admin_even_without_admin_email_configured(): void
    {
        config(['bpmn.admin_email' => null, 'bpmn.admin_password' => null]);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('is_admin', true)->count());
    }

    public function test_seeder_does_not_reset_an_existing_admin_password_on_rerun(): void
    {
        config(['bpmn.admin_email' => null, 'bpmn.admin_password' => null]);

        $this->seed(AdminUserSeeder::class);
        $admin = User::where('is_admin', true)->firstOrFail();
        $originalHash = $admin->password;

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('is_admin', true)->count());
        $this->assertSame($originalHash, $admin->fresh()->password);
    }
}

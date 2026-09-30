<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserListingTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_listing_is_a_semantic_table_with_record_details_and_role_actions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create([
            'name' => 'Local Diagram User',
            'email' => 'local@example.com',
        ]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk()
            ->assertSee('<table', false)
            ->assertSee('<thead ', false)
            ->assertSee('<tbody ', false)
            ->assertSee('scope="col"', false)
            ->assertSee(__('messages.name'))
            ->assertSee(__('messages.email'))
            ->assertSee(__('messages.login_method'))
            ->assertSee(__('messages.administrator_status'))
            ->assertSee(__('messages.user_actions'))
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee(__('messages.login_method_local'))
            ->assertSee(__('messages.not_administrator'))
            ->assertSee(__('messages.grant_administrator'))
            ->assertSee(route('admin.users.update', $user), false)
            ->assertSee('overflow-x-auto', false);
    }

    public function test_empty_admin_user_listing_has_a_semantic_empty_table_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $admin->delete();

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('<tbody ', false)
            ->assertSee('colspan="5"', false)
            ->assertSee(__('messages.no_users_yet'));
    }
}

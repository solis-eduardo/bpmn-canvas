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
            ->assertSee('colspan="6"', false)
            ->assertSee(__('messages.no_users_yet'));
    }

    public function test_listing_filters_by_name_or_email_login_method_and_administrator_and_keeps_them_on_pagination(): void
    {
        config([
            'services.keycloak.client_id' => 'client-id',
            'services.keycloak.client_secret' => 'client-secret',
            'services.keycloak.base_url' => 'https://sso.example.test',
        ]);
        $admin = User::factory()->create(['name' => 'Root Admin', 'email' => 'root@example.com', 'is_admin' => true]);
        User::factory()->create(['name' => 'Alice Local', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Bob SSO', 'email' => 'bob@corp.test', 'login_method' => 'keycloak']);

        $this->actingAs($admin)->get('/admin/users?name=corp.test')
            ->assertOk()->assertSee('Bob SSO')->assertDontSee('Alice Local');
        $this->get('/admin/users?name=alice')
            ->assertOk()->assertSee('Alice Local')->assertDontSee('Bob SSO');
        $this->get('/admin/users?login_method=keycloak')
            ->assertOk()->assertSee('Bob SSO')->assertDontSee('Alice Local')->assertDontSee('Root Admin');
        $this->get('/admin/users?admin=1')
            ->assertOk()->assertSee('Root Admin')->assertDontSee('Alice Local');
        $this->get('/admin/users?admin=0')
            ->assertOk()->assertDontSee('Root Admin')->assertSee('Alice Local');
        $this->get('/admin/users?name=nobody')
            ->assertOk()->assertSee(__('messages.no_users_match_filters'));

        User::factory()->count(25)->create(['login_method' => 'keycloak']);
        $this->get('/admin/users?login_method=keycloak')
            ->assertOk()->assertSee('login_method=keycloak', false)->assertSee('page=2', false);
    }

    public function test_keycloak_login_method_option_only_appears_when_keycloak_is_configured(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()->assertSee('<option value="local"', false)->assertDontSee('<option value="keycloak"', false);

        config([
            'services.keycloak.client_id' => 'client-id',
            'services.keycloak.client_secret' => 'client-secret',
            'services.keycloak.base_url' => 'https://sso.example.test',
        ]);

        $this->get('/admin/users')->assertOk()->assertSee('<option value="keycloak"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The form screens share one shell (components/form/page + card), modelled on
 * the User form: a title, a Back button, a highlighted card and a visible
 * label on every field.
 */
class FormScreensTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: callable(self): string, 1: string, 2: list<string>}>
     */
    public static function screens(): array
    {
        return [
            'user create' => [fn () => route('admin.users.create'), 'admin.users.index', ['name', 'email', 'password']],
            'user edit' => [fn (self $t) => route('admin.users.edit', $t->target), 'admin.users.index', ['name', 'email', 'login_method', 'password']],
            'workspace create' => [fn () => route('workspaces.create'), 'workspaces.index', ['name', 'keycloak_group']],
            'workspace edit' => [fn (self $t) => route('workspaces.edit', $t->workspace), 'workspaces.index', ['name', 'keycloak_group', 'add-member-user_id', 'transfer-owner-user_id']],
            'diagram create' => [fn () => route('diagrams.create'), 'diagrams.index', ['title']],
            'account' => [fn () => route('account.edit'), 'home', ['name', 'email', 'email_notifications_enabled', 'current_password', 'password', 'password_confirmation']],
        ];
    }

    public User $target;

    public Workspace $workspace;

    /**
     * @param  callable(self): string  $url
     * @param  list<string>  $labelledIds
     */
    #[DataProvider('screens')]
    public function test_form_screens_share_the_card_back_button_and_visible_labels(callable $url, string $backRoute, array $labelledIds): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->target = User::factory()->create();
        $this->workspace = Workspace::factory()->create();

        $response = $this->actingAs($admin)->get($url($this));

        $response->assertOk()
            ->assertSee('rounded-lg border border-gray-200 bg-white p-6 shadow-sm', false)
            ->assertSee('href="'.route($backRoute).'"', false)
            ->assertSee('data-icon="arrow-left"', false);

        foreach ($labelledIds as $id) {
            $response->assertSee('for="'.$id.'"', false);
        }
    }
}

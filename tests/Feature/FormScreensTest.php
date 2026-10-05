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
            'workspace edit' => [fn (self $t) => route('workspaces.edit', $t->workspace), 'workspaces.index', ['name', 'keycloak_group', 'members-members', 'user_id-user_id']],
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
            ->assertSee('data-icon="arrow-left"', false)
            ->assertSee('name="submit_action" value="save"', false)
            ->assertSee('name="submit_action" value="exit"', false)
            ->assertSee(__('messages.save_and_exit'))
            ->assertDontSee('Back to');

        foreach ($labelledIds as $id) {
            $response->assertSee('for="'.$id.'"', false);
        }
    }

    public function test_back_buttons_read_just_back_in_every_locale(): void
    {
        foreach (['en' => 'Back', 'pt_BR' => 'Voltar', 'es' => 'Volver'] as $locale => $label) {
            $this->assertSame($label, trans('messages.back', [], $locale));
        }
    }

    public function test_save_and_exit_on_the_user_form_goes_to_the_listing_and_plain_save_stays(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['name' => 'Before']);

        $this->actingAs($admin)->put(route('admin.users.update', $target), ['name' => 'Stay', 'submit_action' => 'save'])
            ->assertRedirect(route('admin.users.edit', $target))
            ->assertSessionHas('status');

        $this->actingAs($admin)->put(route('admin.users.update', $target), ['name' => 'Leave', 'submit_action' => 'exit'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('Leave', $target->fresh()->name);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New', 'email' => 'new@example.com', 'password' => 'password-123', 'submit_action' => 'exit',
        ])->assertRedirect(route('admin.users.index'));
    }

    public function test_save_and_exit_on_the_account_forms_goes_home_and_plain_save_stays(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.update'), ['name' => 'Me', 'submit_action' => 'exit'])
            ->assertRedirect(route('home'));
        $this->actingAs($user)->put(route('account.update'), ['name' => 'Me'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('status', __('messages.saved'));
        $this->actingAs($user)->put(route('account.notifications.update'), ['submit_action' => 'exit'])
            ->assertRedirect(route('home'));
    }

    public function test_save_and_exit_on_the_diagram_form_goes_to_the_listing_and_plain_save_opens_the_editor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('diagrams.store'), ['title' => 'Leave Me', 'submit_action' => 'exit'])
            ->assertRedirect(route('diagrams.index'));
        $this->actingAs($user)->post(route('diagrams.store'), ['title' => 'Open Me', 'submit_action' => 'save'])
            ->assertRedirect(route('diagrams.show', 'open-me'));
    }

    public function test_a_failed_save_never_exits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('account.edit'))->put(route('account.update'), ['name' => '', 'submit_action' => 'exit'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasErrors('name');
    }
}

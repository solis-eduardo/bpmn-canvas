<?php

namespace Tests\Feature\Auth;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\UserRepository;
use App\Repositories\VersionRepository;
use App\Services\Identity\UserDeactivationService;
use App\Services\LockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deactivating a User account (issue #244, ADR-0020).
 */
class UserDeactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function draft(string $label): string
    {
        return preg_replace('/ name="[^"]*"/', ' name="'.$label.'"', Diagram::emptyBpmn(), 1);
    }

    public function test_deactivated_user_cannot_sign_in_locally(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password'), 'login_method' => User::LOGIN_METHOD_LOCAL]);
        app(UserDeactivationService::class)->deactivate($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_reactivated_user_can_sign_in_again(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password'), 'login_method' => User::LOGIN_METHOD_LOCAL]);
        $service = app(UserDeactivationService::class);
        $service->deactivate($user);
        $service->reactivate($user->refresh());

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivation_signs_out_an_existing_session_on_its_next_request(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/deactivate')->assertRedirect();

        // A request still carrying the User's session must be turned away by
        // the active-user check, even when the session wasn't deleted.
        $this->actingAs($user->refresh())->get(route('admin.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_deactivation_releases_locks_and_keeps_the_draft_as_a_provisional_version(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        app(LockService::class)->openOrRequest($diagram, $holder);
        $this->actingAs($holder)
            ->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => $this->draft('unsaved work')])
            ->assertOk();

        app(UserDeactivationService::class)->deactivate($holder);

        $this->assertNull($diagram->refresh()->locked_by);
        $provisional = array_filter(
            app(VersionRepository::class)->list($diagram),
            fn (array $version) => $version['provisional'],
        );
        $this->assertCount(1, $provisional);
    }

    public function test_deactivated_user_is_hidden_from_pickers(): void
    {
        $gone = User::factory()->create(['name' => 'Gone Person']);
        User::factory()->create(['name' => 'Active Person']);

        app(UserDeactivationService::class)->deactivate($gone);

        $names = app(UserRepository::class)->allOrderedByName()->pluck('name');
        $this->assertContains('Active Person', $names);
        $this->assertNotContains('Gone Person', $names);
    }

    public function test_an_administrator_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users/'.$admin->id.'/deactivate')
            ->assertSessionHasErrors('deactivated');

        $this->assertFalse($admin->refresh()->isDeactivated());
    }

    public function test_an_administrator_cannot_demote_themselves_when_the_only_other_administrator_is_deactivated(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => true]);

        $this->actingAs($actor)->post('/admin/users/'.$other->id.'/deactivate');

        $this->actingAs($actor)->put('/admin/users/'.$actor->id, ['is_admin' => 0])
            ->assertSessionHasErrors('is_admin');
        $this->assertTrue($actor->refresh()->is_admin);
    }

    public function test_bulk_transfer_hands_owned_diagrams_and_workspaces_to_a_successor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $leaver = User::factory()->create();
        $successor = User::factory()->create();
        $diagram = Diagram::factory()->for($leaver, 'author')->create(['owner_id' => $leaver->id]);
        $workspace = Workspace::factory()->create(['owner_id' => $leaver->id]);

        app(UserDeactivationService::class)->deactivate($leaver);

        $this->actingAs($admin)
            ->post('/admin/users/'.$leaver->id.'/transfer-ownership', ['successor_id' => $successor->id])
            ->assertRedirect();

        $this->assertSame($successor->id, $diagram->refresh()->owner_id);
        $this->assertSame($successor->id, $workspace->refresh()->owner_id);
        $this->assertSame($leaver->id, $diagram->author_id, 'Authorship is never transferred.');
        $this->assertFalse(Collaborator::where('diagram_id', $diagram->id)->where('user_id', $leaver->id)->exists());
    }

    public function test_ownership_cannot_be_transferred_to_a_deactivated_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $leaver = User::factory()->create();
        $gone = User::factory()->create();
        $diagram = Diagram::factory()->for($leaver, 'author')->create(['owner_id' => $leaver->id]);

        app(UserDeactivationService::class)->deactivate($gone);

        $this->actingAs($admin)
            ->post('/admin/users/'.$leaver->id.'/transfer-ownership', ['successor_id' => $gone->id])
            ->assertSessionHasErrors('successor_id');

        $this->assertSame($leaver->id, $diagram->refresh()->owner_id);
    }

    public function test_a_non_administrator_cannot_deactivate_a_user(): void
    {
        $regular = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($regular)->post('/admin/users/'.$target->id.'/deactivate')->assertForbidden();

        $this->assertFalse($target->refresh()->isDeactivated());
    }
}

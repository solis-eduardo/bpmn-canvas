<?php

namespace Tests\Feature\Auth;

use App\Auth\DeactivatedAwareUserProvider;
use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Member;
use App\Models\Share;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAdded;
use App\Repositories\ListSort;
use App\Repositories\UserRepository;
use App\Repositories\WorkspaceListFilters;
use App\Repositories\WorkspaceRepository;
use App\Services\Identity\UserDeactivationService;
use App\Services\LockService;
use App\Support\Demo;
use App\Support\KeycloakProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * The default scope of User (issue #244, ADR-0020): every query returns active
 * Users only; deactivated ones appear in User administration and in the places
 * that must still find them (login, past records).
 */
class ActiveUserScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function deactivated(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(UserDeactivationService::class)->deactivate($user);

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // ---- the default scope and its opt-ins ---------------------------------

    public function test_queries_return_active_users_only_by_default(): void
    {
        $active = User::factory()->create();
        $gone = $this->deactivated();

        $this->assertTrue(User::all()->contains($active));
        $this->assertFalse(User::all()->contains($gone));
        $this->assertNull(User::find($gone->id));
        $this->assertSame(1, User::count());
        $this->assertNull(User::where('email', $gone->email)->first());
    }

    public function test_with_deactivated_opts_out_of_the_scope(): void
    {
        $active = User::factory()->create();
        $gone = $this->deactivated();

        $all = User::withDeactivated()->get();

        $this->assertTrue($all->contains($active));
        $this->assertTrue($all->contains($gone));
        $this->assertTrue(User::withDeactivated()->findOrFail($gone->id)->isDeactivated());
    }

    public function test_the_repository_hides_deactivated_users_and_its_login_lookups_find_them(): void
    {
        $active = User::factory()->create(['name' => 'Active']);
        $gone = $this->deactivated(['name' => 'Gone', 'keycloak_id' => 'kc-gone']);
        $repository = app(UserRepository::class);

        $this->assertSame([$active->id], $repository->allOrderedByName()->pluck('id')->all());
        $this->assertNull($repository->find($gone->id));
        $this->assertCount(1, $repository->findMany([$active->id, $gone->id]));
        $this->assertNull($repository->findByEmail($gone->email));

        $this->assertTrue($repository->findByEmailForLogin($gone->email)->is($gone));
        $this->assertTrue($repository->findByKeycloakIdForLogin('kc-gone')->is($gone));
        $this->assertNull($repository->findUnlinkedByEmailForLogin($gone->email));
        $unlinked = $this->deactivated();
        $this->assertTrue($repository->findUnlinkedByEmailForLogin($unlinked->email)->is($unlinked));
    }

    public function test_administration_lists_deactivated_users_and_opens_their_screens(): void
    {
        $admin = $this->admin();
        $gone = $this->deactivated(['name' => 'Gone Person']);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Gone Person');
        $this->actingAs($admin)->get(route('admin.users.edit', $gone))->assertOk()->assertSee('Gone Person');

        $this->actingAs($admin)->put(route('admin.users.update', $gone), ['name' => 'Renamed'])->assertRedirect();
        $this->assertSame('Renamed', User::withDeactivated()->find($gone->id)->name);

        $this->actingAs($admin)->post(route('admin.users.reactivate', $gone))->assertRedirect();
        $this->assertFalse(User::findOrFail($gone->id)->isDeactivated());
    }

    public function test_the_admin_user_route_binding_does_not_leak_to_other_routes(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $gone = $this->deactivated();
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $gone->id, 'origin' => Member::ORIGIN_MANUAL]);

        $this->actingAs($owner)->delete(route('workspaces.members.destroy', [$workspace, $gone->id]))->assertNotFound();
    }

    public function test_the_user_edit_screen_offers_only_active_successors(): void
    {
        $admin = $this->admin();
        $leaver = User::factory()->create();
        Diagram::factory()->for($leaver, 'author')->create(['owner_id' => $leaver->id]);
        app(UserDeactivationService::class)->deactivate($leaver);
        User::factory()->create(['name' => 'Successor Active']);
        $this->deactivated(['name' => 'Successor Gone']);

        $this->actingAs($admin)->get(route('admin.users.edit', $leaver))
            ->assertOk()
            ->assertSee('Successor Active')
            ->assertDontSee('Successor Gone');
    }

    // ---- pickers ------------------------------------------------------------

    public function test_pickers_list_active_users_only(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Pick Active']);
        $this->deactivated(['name' => 'Pick Gone']);
        $diagram = Diagram::factory()->for($user, 'author')->for($user, 'owner')->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        foreach (['/diagrams', '/diagrams/create', '/workspaces', '/workspaces/create', "/workspaces/{$workspace->id}/edit", "/diagrams/{$diagram->slug}", "/diagrams/{$diagram->slug}/edit"] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertDontSee('Pick Gone');
        }
        $this->actingAs($user)->get('/diagrams/create')->assertSee('Pick Active');
    }

    public function test_collaborator_and_member_lists_and_counts_skip_deactivated_users(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $activeMember = User::factory()->create();
        $gone = User::factory()->create(['name' => 'Gone Collaborator']);
        foreach ([$activeMember, $gone] as $user) {
            Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $user->id, 'status' => Collaborator::STATUS_APPROVED]);
            Member::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'origin' => Member::ORIGIN_MANUAL]);
        }
        app(UserDeactivationService::class)->deactivate($gone);

        $this->actingAs($owner)->getJson("/diagrams/{$diagram->slug}/collaborators")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertDontSee('Gone Collaborator');

        $listed = app(WorkspaceRepository::class)->search($this->admin(), WorkspaceListFilters::fromRequest(request()), ListSort::fromRequest(request(), WorkspaceRepository::SORT_COLUMNS));
        $this->assertSame(1, $listed->first()->members_count);

        // The rows themselves are kept, so reactivation needs no re-invitation.
        $this->assertDatabaseHas('collaborators', ['diagram_id' => $diagram->id, 'user_id' => $gone->id]);
        $this->assertDatabaseHas('members', ['workspace_id' => $workspace->id, 'user_id' => $gone->id]);
    }

    // ---- previously leaking endpoints ---------------------------------------

    public function test_diagram_settings_rejects_a_deactivated_successor(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $gone = $this->deactivated();

        $this->actingAs($owner)->put("/diagrams/{$diagram->slug}/settings", ['title' => $diagram->title, 'user_id' => $gone->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame($owner->id, $diagram->refresh()->owner_id);
    }

    public function test_workspace_update_rejects_a_deactivated_owner_and_member(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $gone = $this->deactivated();

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}", ['name' => $workspace->name, 'user_id' => $gone->id])
            ->assertSessionHasErrors('user_id');
        $this->actingAs($owner)->put("/workspaces/{$workspace->id}", ['name' => $workspace->name, 'members' => [$owner->id, $gone->id]])
            ->assertSessionHasErrors('members.1');

        $this->assertSame($owner->id, $workspace->refresh()->owner_id);
        $this->assertDatabaseMissing('members', ['workspace_id' => $workspace->id, 'user_id' => $gone->id]);
    }

    public function test_workspace_owner_transfer_rejects_a_deactivated_user_by_id_and_ignores_it_by_email(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $gone = $this->deactivated();

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", ['user_id' => $gone->id])
            ->assertSessionHasErrors('user_id');
        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", ['email' => $gone->email])
            ->assertRedirect(route('workspaces.index'));

        $this->assertSame($owner->id, $workspace->refresh()->owner_id);
    }

    public function test_diagram_owner_transfer_rejects_a_deactivated_user_by_id_and_ignores_it_by_email(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $gone = $this->deactivated();

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", ['user_id' => $gone->id])->assertUnprocessable();
        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", ['email' => $gone->email])->assertOk()->assertJson(['success' => true]);

        $this->assertSame($owner->id, $diagram->refresh()->owner_id);
    }

    public function test_inviting_a_deactivated_user_as_collaborator_is_refused_by_id_and_ignored_by_email(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $gone = $this->deactivated();

        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", ['user_id' => $gone->id])->assertUnprocessable();
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", ['email' => $gone->email]);

        $this->assertDatabaseMissing('collaborators', ['diagram_id' => $diagram->id, 'user_id' => $gone->id]);
    }

    public function test_adding_a_deactivated_user_as_workspace_member_is_refused_or_ignored(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $gone = $this->deactivated();

        $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", ['user_id' => $gone->id])->assertSessionHasErrors('user_id');
        $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", ['email' => $gone->email])->assertRedirect();
        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/members", ['members' => [$owner->id, $gone->id]])->assertSessionHasErrors('members.1');

        $this->assertDatabaseMissing('members', ['workspace_id' => $workspace->id, 'user_id' => $gone->id]);
    }

    public function test_new_diagram_and_new_workspace_reject_deactivated_picks(): void
    {
        $user = User::factory()->create();
        $gone = $this->deactivated();

        $this->actingAs($user)->post('/diagrams', ['title' => 'Picked', 'collaborators' => [$gone->id]])
            ->assertSessionHasErrors('collaborators.0');
        $this->actingAs($user)->post('/workspaces', ['name' => 'Picked', 'members' => [$gone->id]])
            ->assertSessionHasErrors('members.0');
    }

    public function test_bulk_transfer_rejects_a_deactivated_successor(): void
    {
        $admin = $this->admin();
        $leaver = $this->deactivated();
        $gone = $this->deactivated();
        $diagram = Diagram::factory()->for($leaver, 'author')->create(['owner_id' => $leaver->id]);

        $this->actingAs($admin)->post(route('admin.users.transfer-ownership', $leaver), ['successor_id' => $gone->id])
            ->assertSessionHasErrors('successor_id');

        $this->assertSame($leaver->id, $diagram->refresh()->owner_id);
    }

    public function test_lock_transfer_to_a_deactivated_user_is_refused(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->for($holder, 'owner')->create();
        app(LockService::class)->openOrRequest($diagram, $holder);
        $gone = $this->deactivated();

        $this->actingAs($holder)->putJson("/diagrams/{$diagram->slug}/lock", ['user_id' => $gone->id])
            ->assertStatus(403);

        $this->assertSame($holder->id, $diagram->refresh()->locked_by);
    }

    // ---- existing records still resolve --------------------------------------

    public function test_relations_still_resolve_a_deactivated_user(): void
    {
        $gone = User::factory()->create(['name' => 'Past Person']);
        $diagram = Diagram::factory()->for($gone, 'author')->for($gone, 'owner')->create();
        $diagram->forceFill(['locked_by' => $gone->id, 'lock_requested_by' => $gone->id])->save();
        $workspace = Workspace::factory()->create(['owner_id' => $gone->id]);
        $share = Share::factory()->for($diagram)->for($gone, 'author')->create();
        $collaborator = Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $gone->id, 'status' => Collaborator::STATUS_APPROVED]);
        $member = Member::create(['workspace_id' => $workspace->id, 'user_id' => $gone->id, 'origin' => Member::ORIGIN_MANUAL]);
        User::withDeactivated()->whereKey($gone->id)->update(['deactivated_at' => now()]);

        $fresh = Diagram::findOrFail($diagram->id);
        $this->assertSame('Past Person', $fresh->author->name);
        $this->assertSame('Past Person', $fresh->owner->name);
        $this->assertSame('Past Person', $fresh->lockedBy->name);
        $this->assertSame('Past Person', $fresh->lockRequestedBy->name);
        $this->assertSame('Past Person', $workspace->fresh()->owner->name);
        $this->assertSame('Past Person', $share->fresh()->author->name);
        $this->assertSame('Past Person', $collaborator->fresh()->user->name);
        $this->assertSame('Past Person', $member->fresh()->user->name);

        $this->actingAs($this->admin())->get('/diagrams')->assertOk()->assertSee('Past Person');
        $this->actingAs($this->admin())->get('/workspaces')->assertOk()->assertSee('Past Person');
    }

    public function test_deactivating_a_lock_holder_still_keeps_the_provisional_version_authored_by_them(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->for($holder, 'owner')->create();
        app(LockService::class)->openOrRequest($diagram, $holder);

        app(UserDeactivationService::class)->deactivate($holder);

        $this->assertNull($diagram->refresh()->locked_by);
    }

    // ---- authentication ------------------------------------------------------

    public function test_the_auth_provider_still_resolves_a_deactivated_user(): void
    {
        $gone = $this->deactivated();
        User::withDeactivated()->whereKey($gone->id)->update(['remember_token' => 'tok']);
        $provider = Auth::guard()->getProvider();

        $this->assertInstanceOf(DeactivatedAwareUserProvider::class, $provider);
        $this->assertTrue($provider->retrieveById($gone->id)->is($gone));
        $this->assertTrue($provider->retrieveByToken($gone->id, 'tok')->is($gone));
    }

    public function test_a_surviving_session_of_a_deactivated_user_is_signed_out_with_the_proper_message(): void
    {
        $gone = $this->deactivated();

        $this->actingAs($gone)->get('/diagrams')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', __('messages.account_deactivated'));
        $this->assertGuest();
    }

    public function test_local_login_is_refused_for_a_deactivated_user(): void
    {
        $gone = $this->deactivated(['password' => bcrypt('secret-pass'), 'login_method' => User::LOGIN_METHOD_LOCAL]);

        $this->post('/login', ['email' => $gone->email, 'password' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_keycloak_login_is_refused_for_a_deactivated_user_matched_by_sub_or_by_email(): void
    {
        $bySub = $this->deactivated(['keycloak_id' => 'kc-sub', 'login_method' => User::LOGIN_METHOD_KEYCLOAK]);
        $byEmail = $this->deactivated(['email' => 'linked@example.com']);

        foreach ([['kc-sub', $bySub->email], ['kc-new', 'linked@example.com']] as [$sub, $email]) {
            $this->mockKeycloak($sub, $email);

            $this->get('/login/keycloak/callback')
                ->assertRedirect('/login')
                ->assertSessionHas('error', __('messages.account_deactivated'));
            $this->assertGuest();
        }

        $this->assertNull($byEmail->refresh()->keycloak_id, 'a refused login links nothing');
        $this->assertSame(2, User::withDeactivated()->count(), 'a refused login provisions nothing');
    }

    public function test_demo_sign_in_is_refused_for_a_deactivated_account(): void
    {
        config(['bpmn.demo_mode' => true, 'bpmn.version_store' => 'database']);
        $this->artisan('db:seed', ['--class' => 'DemoSeeder']);
        $email = Demo::ACCOUNTS['author']['email'];
        app(UserDeactivationService::class)->deactivate(User::where('email', $email)->firstOrFail());

        $this->post(route('demo.sign-in', 'author'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', __('messages.account_deactivated'));
        $this->assertGuest();
    }

    private function mockKeycloak(string $sub, string $email): void
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $jwt = $encode(['alg' => 'RS256']).'.'.$encode(['email_verified' => true, 'groups' => [], 'realm_access' => ['roles' => []]]).'.sig';
        $provider = Mockery::mock(KeycloakProvider::class);
        $provider->shouldReceive('user')->andReturn(SocialiteUser::fake(['id' => $sub, 'nickname' => $email, 'name' => 'Kc', 'email' => $email, 'token' => $jwt]));
        $provider->shouldReceive('idToken')->andReturn(null);
        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }

    // ---- notifications -------------------------------------------------------

    public function test_a_deactivated_user_receives_no_notifications(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $gone = $this->deactivated();
        $active = User::factory()->create();

        $gone->notify(new WorkspaceMemberAdded($workspace));
        $active->notify(new WorkspaceMemberAdded($workspace));

        $this->assertSame(0, $gone->notifications()->count());
        $this->assertSame(1, $active->notifications()->count());
    }

    public function test_a_workspace_with_a_deactivated_owner_can_still_save_its_members(): void
    {
        $admin = $this->admin();
        $gone = $this->deactivated();
        $workspace = Workspace::factory()->create(['owner_id' => $gone->id]);
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $gone->id, 'origin' => Member::ORIGIN_MANUAL]);
        $added = User::factory()->create();

        $this->actingAs($admin)->put("/workspaces/{$workspace->id}/members", ['members' => [$added->id]])->assertRedirect();

        $this->assertDatabaseHas('members', ['workspace_id' => $workspace->id, 'user_id' => $added->id]);
        $this->assertDatabaseHas('members', ['workspace_id' => $workspace->id, 'user_id' => $gone->id]);
    }

    public function test_collaborator_and_member_routes_do_not_resolve_a_deactivated_user(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $gone = $this->deactivated();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $gone->id, 'status' => Collaborator::STATUS_APPROVED]);

        $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$gone->id}")->assertNotFound();

        $this->assertDatabaseHas('collaborators', ['diagram_id' => $diagram->id, 'user_id' => $gone->id]);
    }

    public function test_keycloak_email_refresh_treats_a_deactivated_holder_as_holding_the_email(): void
    {
        $gone = $this->deactivated(['email' => 'taken'.'@example.com']);
        $user = User::factory()->create(['keycloak_id' => 'kc-live', 'login_method' => User::LOGIN_METHOD_KEYCLOAK]);
        $this->mockKeycloak('kc-live', $gone->email);

        $this->get('/login/keycloak/callback')->assertRedirect(route('home'));

        $this->assertNotSame($gone->email, $user->refresh()->email);
    }
}

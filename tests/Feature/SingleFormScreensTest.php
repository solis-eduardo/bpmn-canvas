<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Screens whose related settings live in one form (profile, Workspace) and
 * the Users picked from a list rather than typed by email.
 */
class SingleFormScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_the_profile_form_holds_the_notification_preference_and_the_password_change(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.edit'))->assertOk();

        $response->assertSee('action="'.route('account.update').'"', false)
            ->assertSee('name="email_notifications_enabled"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee(route('account.notifications.update'), false)
            ->assertDontSee(route('account.password.update'), false);
    }

    public function test_the_profile_form_saves_name_notifications_and_password_together(): void
    {
        $user = User::factory()->create(['password' => 'old-password', 'email_notifications_enabled' => true]);

        $this->actingAs($user)->put(route('account.update'), [
            'name' => 'New Name',
            'email_notifications_enabled' => '0',
            'current_password' => 'old-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertFalse($user->email_notifications_enabled);
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
    }

    public function test_the_profile_form_leaves_the_password_alone_when_none_is_typed(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $hash = $user->password;

        $this->actingAs($user)->put(route('account.update'), ['name' => 'Same Password', 'password' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_a_new_password_needs_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->from(route('account.edit'))->put(route('account.update'), [
            'name' => $user->name,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_the_workspace_form_saves_members_and_ownership_transfer_with_the_data(): void
    {
        $owner = User::factory()->create();
        $kept = User::factory()->create();
        $dropped = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Old name']);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $dropped);

        $this->actingAs($owner)->put(route('workspaces.update', $workspace), [
            'name' => 'New name',
            'members' => [$owner->id, $kept->id],
            'user_id' => $newOwner->id,
        ])->assertRedirect(route('workspaces.index'));

        $workspace->refresh();
        $this->assertSame('New name', $workspace->name);
        $this->assertSame($newOwner->id, $workspace->owner_id);

        $members = $workspace->members()->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing([$owner->id, $kept->id, $newOwner->id], $members);
    }

    public function test_the_workspace_form_still_refuses_to_drop_the_owner_from_the_members(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);

        $this->actingAs($owner)->from(route('workspaces.edit', $workspace))->put(route('workspaces.update', $workspace), [
            'name' => $workspace->name,
            'members' => [$other->id],
        ])->assertSessionHasErrors('members');

        $this->assertTrue($workspace->members()->where('user_id', $owner->id)->exists());
    }

    public function test_the_workspace_creation_form_adds_the_picked_users_as_members(): void
    {
        $creator = User::factory()->create();
        $picked = User::factory()->create(['name' => 'Wes Picked']);

        $this->actingAs($creator)->get(route('workspaces.create'))
            ->assertOk()
            ->assertSee('name="members[]"', false)
            ->assertSee('Wes Picked');

        $this->actingAs($creator)->post(route('workspaces.store'), [
            'name' => 'Fresh space',
            'members' => [$picked->id, $creator->id],
        ])->assertRedirect();

        $workspace = Workspace::where('name', 'Fresh space')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$creator->id, $picked->id],
            $workspace->members()->pluck('user_id')->all(),
        );
        $this->assertSame($creator->id, $workspace->owner_id);
    }

    public function test_the_diagram_form_grants_the_picked_users_access_on_creation(): void
    {
        $creator = User::factory()->create();
        $picked = User::factory()->create(['name' => 'Pat Picked']);

        $this->actingAs($creator)->get('/diagrams/create')
            ->assertOk()
            ->assertSee('name="collaborators[]"', false)
            ->assertSee('Pat Picked');

        $this->actingAs($creator)->post('/diagrams', [
            'title' => 'With people',
            'collaborators' => [$picked->id, $creator->id],
        ])->assertRedirect();

        $diagram = Diagram::where('slug', 'with-people')->firstOrFail();
        $collaborators = Collaborator::where('diagram_id', $diagram->id)->get();

        $this->assertCount(1, $collaborators);
        $this->assertSame($picked->id, $collaborators->first()->user_id);
        $this->assertSame('approved', $collaborators->first()->status);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DiagramCollaboratorInvited;
use App\Notifications\DiagramCollaboratorRemoved;
use App\Notifications\DiagramCollaboratorRoleChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Diagram Manager: a Collaborator who can also invite and remove plain
 * Collaborators (CONTEXT.md, "Diagram Manager"; ADR-0017).
 */
class DiagramManagerTest extends TestCase
{
    use RefreshDatabase;

    private Diagram $diagram;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');

        $this->diagram = Diagram::factory()->create();
        $this->owner = $this->diagram->owner;
        $this->manager = $this->collaborator(Collaborator::ROLE_MANAGER);
    }

    private function collaborator(string $role = Collaborator::ROLE_COLLABORATOR, string $status = Collaborator::STATUS_APPROVED): User
    {
        $user = User::factory()->create();
        Collaborator::create(['diagram_id' => $this->diagram->id, 'user_id' => $user->id, 'status' => $status, 'role' => $role]);

        return $user;
    }

    private function url(?User $user = null): string
    {
        return "/diagrams/{$this->diagram->slug}/collaborators".($user ? "/{$user->id}" : '');
    }

    private function roleOf(User $user): ?string
    {
        return Collaborator::where('diagram_id', $this->diagram->id)->where('user_id', $user->id)->value('role');
    }

    public function test_the_owner_invites_a_diagram_manager_and_it_is_persisted(): void
    {
        Notification::fake();
        $target = User::factory()->create();

        $this->actingAs($this->owner)->postJson($this->url(), ['user_id' => $target->id, 'role' => 'manager'])
            ->assertCreated()
            ->assertJsonPath('status', 'approved');

        $this->assertSame('manager', $this->roleOf($target));
        Notification::assertSentTo($target, DiagramCollaboratorInvited::class, fn ($n) => $n->toArray($target)['role'] === 'manager');
    }

    public function test_an_invite_without_a_role_is_a_plain_collaborator_and_keeps_an_existing_role(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->owner)->postJson($this->url(), ['user_id' => $target->id])->assertCreated();
        $this->assertSame('collaborator', $this->roleOf($target));

        $this->actingAs($this->owner)->postJson($this->url(), ['user_id' => $this->manager->id])->assertCreated();
        $this->assertSame('manager', $this->roleOf($this->manager));
    }

    public function test_the_owner_promotes_and_demotes_and_the_user_is_notified(): void
    {
        Notification::fake();
        $plain = $this->collaborator();

        $this->actingAs($this->owner)->putJson($this->url($plain), ['role' => 'manager'])->assertOk()->assertJsonPath('role', 'manager');
        $this->assertSame('manager', $this->roleOf($plain));
        Notification::assertSentTo($plain, DiagramCollaboratorRoleChanged::class, fn ($n) => $n->toArray($plain)['role'] === 'manager');

        $this->actingAs($this->owner)->putJson($this->url($plain), ['role' => 'collaborator'])->assertOk();
        $this->assertSame('collaborator', $this->roleOf($plain));
        Notification::assertSentToTimes($plain, DiagramCollaboratorRoleChanged::class, 2);
    }

    public function test_an_administrator_can_assign_roles(): void
    {
        $plain = $this->collaborator();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->putJson($this->url($plain), ['role' => 'manager'])->assertOk();

        $this->assertSame('manager', $this->roleOf($plain));
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $plain = $this->collaborator();

        $this->actingAs($this->owner)->putJson($this->url($plain), ['role' => 'boss'])->assertUnprocessable();
    }

    public function test_a_manager_lists_invites_and_removes_plain_collaborators(): void
    {
        Notification::fake();
        $plain = $this->collaborator();
        $target = User::factory()->create();

        $this->actingAs($this->manager)->getJson($this->url())->assertOk()->assertJsonFragment(['role' => 'manager']);

        $this->actingAs($this->manager)->postJson($this->url(), ['user_id' => $target->id])->assertCreated();
        $this->assertSame('collaborator', $this->roleOf($target));

        $this->actingAs($this->manager)->deleteJson($this->url($plain))->assertOk();
        $this->assertNull($this->roleOf($plain));
        Notification::assertSentTo($plain, DiagramCollaboratorRemoved::class);
    }

    public function test_a_manager_approves_a_request_as_a_plain_collaborator(): void
    {
        $requester = $this->collaborator(status: Collaborator::STATUS_REQUESTED);

        $this->actingAs($this->manager)->putJson($this->url($requester))->assertOk()->assertJsonPath('role', 'collaborator');

        $this->assertDatabaseHas('collaborators', ['user_id' => $requester->id, 'status' => 'approved', 'role' => 'collaborator']);
    }

    public function test_a_manager_cannot_touch_diagram_managers(): void
    {
        $other = $this->collaborator(Collaborator::ROLE_MANAGER);
        $plain = $this->collaborator();
        $target = User::factory()->create();

        $this->actingAs($this->manager)->postJson($this->url(), ['user_id' => $target->id, 'role' => 'manager'])->assertForbidden();
        $this->actingAs($this->manager)->putJson($this->url($plain), ['role' => 'manager'])->assertForbidden();
        $this->actingAs($this->manager)->putJson($this->url($other), ['role' => 'collaborator'])->assertForbidden();
        $this->actingAs($this->manager)->putJson($this->url($this->manager), ['role' => 'collaborator'])->assertForbidden();
        $this->actingAs($this->manager)->deleteJson($this->url($other))->assertForbidden();

        $this->assertNull($this->roleOf($target));
        $this->assertSame('collaborator', $this->roleOf($plain));
        $this->assertSame('manager', $this->roleOf($other));
    }

    public function test_a_manager_can_leave(): void
    {
        $this->actingAs($this->manager)->deleteJson($this->url($this->manager))->assertOk();

        $this->assertNull($this->roleOf($this->manager));
    }

    public function test_the_owner_removes_a_manager(): void
    {
        $this->actingAs($this->owner)->deleteJson($this->url($this->manager))->assertOk();

        $this->assertNull($this->roleOf($this->manager));
    }

    public function test_a_manager_gains_no_owner_only_powers(): void
    {
        $this->actingAs($this->manager)->deleteJson("/diagrams/{$this->diagram->slug}")->assertForbidden();
        // Shares are open to every approved Collaborator (ADR-0016), Managers included.
        $this->assertTrue($this->manager->can('manageShares', $this->diagram));
        $this->assertFalse($this->manager->can('transferOwnership', $this->diagram));
        $this->assertFalse($this->manager->can('putInWorkspace', $this->diagram));
    }

    public function test_others_still_get_403_on_collaborator_management(): void
    {
        $plain = $this->collaborator();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->addMember($workspace, $member);
        $this->diagram->update(['workspace_id' => $workspace->id]);
        $stranger = User::factory()->create();
        $pending = $this->collaborator(Collaborator::ROLE_MANAGER, Collaborator::STATUS_REQUESTED);
        $target = User::factory()->create();

        foreach ([$plain, $member] as $user) {
            $this->actingAs($user)->getJson($this->url())->assertForbidden();
            $this->actingAs($user)->postJson($this->url(), ['user_id' => $target->id])->assertForbidden();
            $this->actingAs($user)->putJson($this->url($target))->assertForbidden();
            $this->actingAs($user)->putJson($this->url($plain), ['role' => 'manager'])->assertForbidden();
            $this->actingAs($user)->deleteJson($this->url($plain))->assertForbidden();
        }

        // The Diagram sits in a Workspace, hidden from a stranger altogether.
        $this->actingAs($stranger)->postJson($this->url(), ['user_id' => $target->id])->assertNotFound();
        $this->diagram->update(['workspace_id' => null]);

        foreach ([$stranger, $pending] as $user) {
            $this->actingAs($user)->getJson($this->url())->assertForbidden();
            $this->actingAs($user)->postJson($this->url(), ['user_id' => $target->id])->assertForbidden();
            $this->actingAs($user)->putJson($this->url($target))->assertForbidden();
            $this->actingAs($user)->putJson($this->url($plain), ['role' => 'manager'])->assertForbidden();
            $this->actingAs($user)->deleteJson($this->url($plain))->assertForbidden();
        }

        $this->assertSame('collaborator', $this->roleOf($plain));
    }

    public function test_a_requester_cannot_pick_a_role_for_themselves(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson($this->url(), ['user_id' => $stranger->id, 'role' => 'manager'])->assertForbidden();
        $this->assertNull($this->roleOf($stranger));
    }

    public function test_the_previous_owner_becomes_a_plain_collaborator_on_transfer(): void
    {
        $new = User::factory()->create();

        $this->actingAs($this->owner)->putJson("/diagrams/{$this->diagram->slug}/owner", ['user_id' => $new->id])->assertOk();

        $this->assertSame('collaborator', $this->roleOf($this->owner));
    }

    public function test_the_collaborators_entry_is_shown_to_a_manager_with_role_controls_hidden(): void
    {
        $this->actingAs($this->manager)->get("/diagrams/{$this->diagram->slug}")
            ->assertOk()
            ->assertSee('id="btn-collaborators-entry"', false)
            ->assertSee('data-can-assign-roles="false"', false)
            ->assertDontSee('id="collab-invite-role"', false);
    }

    public function test_the_owner_sees_the_role_controls(): void
    {
        $this->actingAs($this->owner)->get("/diagrams/{$this->diagram->slug}")
            ->assertSee('data-can-assign-roles="true"', false)
            ->assertSee('id="collab-invite-role"', false);
    }
}

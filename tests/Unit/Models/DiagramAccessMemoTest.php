<?php

namespace Tests\Unit\Models;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CollaboratorService;
use App\Services\DiagramOwnerService;
use App\Services\DiagramWorkspaceService;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Issue #241: the access predicates are memoized per request, across Diagram
 * instances, and every kind of access change must drop the memo.
 */
class DiagramAccessMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('diagrams');
    }

    public function test_the_memo_is_shared_across_instances_of_the_same_diagram(): void
    {
        $diagram = Diagram::factory()->create();
        $other = Diagram::find($diagram->id);
        $stranger = User::factory()->create();

        $queries = $this->countQueries(function () use ($diagram, $other, $stranger) {
            $this->assertTrue($diagram->isEditableBy($diagram->owner));
            $this->assertTrue($other->isEditableBy($diagram->owner));
            $this->assertFalse($diagram->isEditableBy($stranger));
            $this->assertFalse($other->refresh()->isEditableBy($stranger));
            $this->assertFalse($diagram->hasApprovedCollaborator($stranger));
            $this->assertFalse($other->hasApprovedCollaborator($stranger));
            $this->assertFalse($diagram->hasManagerRole($stranger));
            $this->assertFalse($other->hasManagerRole($stranger));
        });

        // $diagram->owner (one lookup), two editable checks, one collaborator
        // check, one manager check, and the refresh() read.
        $this->assertSame(6, $queries);
    }

    public function test_a_member_removed_through_the_member_service_loses_access(): void
    {
        [$workspace, $diagram, $member] = $this->workspaceDiagramWithMember();
        $this->assertTrue($diagram->isEditableBy($member));

        app(MemberService::class)->remove($workspace, $member);

        $this->assertFalse($diagram->isEditableBy($member));
        $this->assertFalse(Diagram::find($diagram->id)->isEditableBy($member));
    }

    public function test_group_memberships_dropped_by_a_keycloak_sync_lose_access(): void
    {
        $workspace = Workspace::factory()->create(['keycloak_group' => 'team-a']);
        $diagram = Diagram::factory()->create(['workspace_id' => $workspace->id]);
        $user = User::factory()->create();
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'origin' => Member::ORIGIN_GROUP]);
        $this->assertTrue($diagram->isEditableBy($user));

        app(MemberService::class)->syncFromKeycloakGroups($user, []);

        $this->assertFalse($diagram->isEditableBy($user));
    }

    public function test_a_member_added_gains_access(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create(['workspace_id' => $workspace->id]);
        $user = User::factory()->create();
        $this->assertFalse($diagram->isEditableBy($user));

        app(MemberService::class)->add($workspace, $user);

        $this->assertTrue(Diagram::find($diagram->id)->isEditableBy($user));
        $this->assertTrue($diagram->isEditableBy($user));
    }

    public function test_a_collaborator_revoked_through_the_collaborator_service_loses_access(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        app(CollaboratorService::class)->grant($diagram, $user, Collaborator::ROLE_MANAGER);
        $this->assertTrue($diagram->isEditableBy($user));
        $this->assertTrue($diagram->hasApprovedCollaborator($user));
        $this->assertTrue($diagram->hasManagerRole($user));

        app(CollaboratorService::class)->revoke(Diagram::find($diagram->id), $user);

        $this->assertFalse($diagram->isEditableBy($user));
        $this->assertFalse($diagram->hasApprovedCollaborator($user));
        $this->assertFalse($diagram->hasManagerRole($user));
    }

    public function test_approving_a_pending_request_grants_access(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        app(CollaboratorService::class)->requestAccess($diagram, $user);
        $this->assertFalse($diagram->isEditableBy($user));
        $this->assertFalse($diagram->hasApprovedCollaborator($user));

        app(CollaboratorService::class)->approve(Diagram::find($diagram->id), $user);

        $this->assertTrue($diagram->isEditableBy($user));
        $this->assertTrue($diagram->hasApprovedCollaborator($user));
    }

    public function test_demoting_a_manager_drops_the_manager_role(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        app(CollaboratorService::class)->grant($diagram, $user, Collaborator::ROLE_MANAGER);
        $this->assertTrue($diagram->hasManagerRole($user));

        app(CollaboratorService::class)->grant(Diagram::find($diagram->id), $user, Collaborator::ROLE_COLLABORATOR);

        $this->assertFalse($diagram->hasManagerRole($user));
        $this->assertTrue($diagram->hasApprovedCollaborator($user));
    }

    public function test_an_ownership_transfer_moves_access_with_it(): void
    {
        $diagram = Diagram::factory()->create();
        $previousOwner = $diagram->owner;
        $newOwner = User::factory()->create();
        $this->assertTrue($diagram->isEditableBy($previousOwner));
        $this->assertFalse($diagram->isEditableBy($newOwner));

        app(DiagramOwnerService::class)->transfer(Diagram::find($diagram->id), $newOwner, giveUpAccess: true);

        $this->assertFalse($diagram->isEditableBy($previousOwner));
        $this->assertTrue($diagram->isEditableBy($newOwner));
    }

    public function test_assigning_and_removing_a_workspace_changes_a_members_access(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create();
        $member = User::factory()->create();
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'origin' => Member::ORIGIN_MANUAL]);
        $this->assertFalse($diagram->isEditableBy($member));

        app(DiagramWorkspaceService::class)->assign(Diagram::find($diagram->id), $workspace);
        $this->assertTrue($diagram->isEditableBy($member));

        app(DiagramWorkspaceService::class)->remove(Diagram::find($diagram->id));
        $this->assertFalse($diagram->isEditableBy($member));
    }

    public function test_deleting_the_workspace_removes_its_members_access(): void
    {
        [$workspace, $diagram, $member] = $this->workspaceDiagramWithMember();
        $this->assertTrue($diagram->isEditableBy($member));

        $workspace->delete();

        $this->assertFalse($diagram->isEditableBy($member));
    }

    public function test_deleting_a_user_removes_the_access_cascaded_from_them(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        app(CollaboratorService::class)->grant($diagram, $user);
        $this->assertTrue($diagram->hasApprovedCollaborator($user));

        $user->delete();

        $this->assertFalse(Diagram::find($diagram->id)->hasApprovedCollaborator($user));
    }

    public function test_the_memo_is_forgotten_between_requests(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        $this->assertFalse($diagram->isEditableBy($user));

        // A write the model events never see.
        DB::table('diagrams')->where('id', $diagram->id)->update(['owner_id' => $user->id]);
        $this->get('/up');

        $this->assertTrue($diagram->isEditableBy($user));
    }

    /**
     * @return array{Workspace, Diagram, User}
     */
    private function workspaceDiagramWithMember(): array
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create(['workspace_id' => $workspace->id]);
        $member = User::factory()->create();
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'origin' => Member::ORIGIN_MANUAL]);

        return [$workspace, $diagram, $member];
    }

    /**
     * @param  callable(): void  $callback
     */
    private function countQueries(callable $callback): int
    {
        $count = 0;

        DB::listen(function () use (&$count) {
            $count++;
        });

        $callback();

        return $count;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DiagramAccessRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestsEmailEnumerationPrevention;

/**
 * Managing Collaborators (invite/approve/remove) is Diagram Owner or
 * Administrator (CONTEXT.md, "Collaborator"; ADR-0006). These tests focus
 * on Diagrams where the Diagram Owner and the Author differ, to prove
 * management follows the Owner, not the Author (issue #13).
 */
class DiagramCollaboratorTest extends TestCase
{
    use RefreshDatabase;
    use TestsEmailEnumerationPrevention;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_only_the_diagram_owner_or_an_admin_can_list_collaborators_when_owner_and_author_differ(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $this->actingAs($author)->getJson("/diagrams/{$diagram->slug}/collaborators")
            ->assertForbidden();

        $this->actingAs($owner)->getJson("/diagrams/{$diagram->slug}/collaborators")
            ->assertOk();

        $this->actingAs($admin)->getJson("/diagrams/{$diagram->slug}/collaborators")
            ->assertOk();
    }

    public function test_only_the_diagram_owner_or_an_admin_can_invite_a_collaborator_directly(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $target->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', Collaborator::STATUS_APPROVED);
    }

    public function test_the_author_alone_cannot_invite_a_collaborator_directly_once_ownership_diverges(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        // The Author is neither the Diagram Owner nor the target, so this
        // is treated as a self-request attempt on someone else's behalf
        // and rejected.
        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $target->id,
        ]);

        $response->assertForbidden();
    }

    public function test_a_workspace_member_who_already_edits_the_diagram_cannot_file_an_access_request(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $member);
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['workspace_id' => $workspace->id]);

        $this->actingAs($member)->postJson("/diagrams/{$diagram->slug}/collaborators", ['user_id' => $member->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('collaborators', ['diagram_id' => $diagram->id, 'user_id' => $member->id]);
        Notification::assertNotSentTo($owner, DiagramAccessRequested::class);
    }

    public function test_only_the_diagram_owner_or_an_admin_approves_a_pending_request(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => Collaborator::STATUS_REQUESTED,
        ]);

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/collaborators/{$requester->id}")
            ->assertForbidden();

        $response = $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/collaborators/{$requester->id}");
        $response->assertOk();
        $response->assertJsonPath('status', Collaborator::STATUS_APPROVED);
    }

    public function test_only_the_diagram_owner_or_an_admin_removes_a_collaborator(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $this->actingAs($author)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('collaborators', ['user_id' => $collaborator->id]);

        $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}")
            ->assertOk();
        $this->assertDatabaseMissing('collaborators', ['user_id' => $collaborator->id]);
    }

    public function test_inviting_by_email_returns_identical_response_for_existing_and_non_existing_email(): void
    {
        $owner = User::factory()->create();
        $existingUser = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $responseWithExistingEmail = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'email' => $existingUser->email,
        ]);

        $responseWithNonExistingEmail = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertEmailEnumerationPrevented($responseWithExistingEmail, $responseWithNonExistingEmail, 201);
    }
}

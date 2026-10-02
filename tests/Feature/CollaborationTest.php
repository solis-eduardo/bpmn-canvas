<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_author_can_invite_a_collaborator_directly_with_immediate_access(): void
    {
        $author = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $collaborator->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => 'approved',
        ]);

        $this->actingAs($collaborator)->get("/diagrams/{$diagram->slug}")->assertOk();
    }

    public function test_author_can_invite_a_collaborator_by_email(): void
    {
        $author = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'email' => $collaborator->email,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => 'approved',
        ]);
    }

    public function test_a_user_can_request_access_to_a_diagram_that_is_not_theirs(): void
    {
        $author = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $response = $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => 'requested',
        ]);
    }

    public function test_a_pending_request_still_blocks_editing_until_approved(): void
    {
        $author = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $requester->id,
        ])->assertCreated();

        $this->actingAs($requester)->get("/diagrams/{$diagram->slug}")->assertForbidden();
        $this->actingAs($requester)->getJson("/diagrams/{$diagram->slug}/content")->assertForbidden();
        $this->actingAs($requester)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions>x</bpmn:definitions>',
        ])->assertForbidden();
    }

    public function test_author_sees_and_approves_a_pending_request(): void
    {
        $author = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => 'requested',
        ]);

        $listing = $this->actingAs($author)->getJson("/diagrams/{$diagram->slug}/collaborators");
        $listing->assertOk();
        $listing->assertJsonFragment(['user_id' => $requester->id, 'status' => 'requested']);

        $approve = $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/collaborators/{$requester->id}");
        $approve->assertOk();

        $this->assertDatabaseHas('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => 'approved',
        ]);

        $this->actingAs($requester)->get("/diagrams/{$diagram->slug}")->assertOk();
    }

    public function test_author_can_reject_a_pending_request(): void
    {
        $author = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => 'requested',
        ]);

        $response = $this->actingAs($author)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$requester->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($requester)->get("/diagrams/{$diagram->slug}")->assertForbidden();
    }

    public function test_admin_can_grant_collaborator_access_on_a_diagram_of_another_author(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $response = $this->actingAs($admin)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $collaborator->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => 'approved',
        ]);
    }

    public function test_author_can_remove_an_approved_collaborator_at_any_time(): void
    {
        $author = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => 'approved',
        ]);

        $this->actingAs($collaborator)->get("/diagrams/{$diagram->slug}")->assertOk();

        $response = $this->actingAs($author)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
        ]);

        $this->actingAs($collaborator)->get("/diagrams/{$diagram->slug}")->assertForbidden();
    }

    public function test_a_user_who_is_neither_author_nor_approved_collaborator_is_blocked_from_editing(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $this->actingAs($outsider)->get("/diagrams/{$diagram->slug}")->assertForbidden();
        $this->actingAs($outsider)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions>x</bpmn:definitions>',
        ])->assertForbidden();
    }

    public function test_the_general_listing_remains_visible_to_any_authenticated_user(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create(['title' => "Another User's Diagram"]);

        $response = $this->actingAs($outsider)->get('/diagrams');

        $response->assertOk();
        $response->assertSee("Another User's Diagram");
    }

    public function test_a_non_author_non_admin_cannot_add_someone_else_as_collaborator(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $someoneElse = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $response = $this->actingAs($outsider)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $someoneElse->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $someoneElse->id,
        ]);
    }

    public function test_a_non_author_non_admin_cannot_approve_or_remove_collaborators(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => 'requested',
        ]);

        $this->actingAs($outsider)->putJson("/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}")
            ->assertForbidden();

        $this->actingAs($outsider)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}")
            ->assertForbidden();
    }

    public function test_a_self_request_never_downgrades_an_already_approved_collaborator(): void
    {
        $author = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $this->actingAs($collaborator)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $collaborator->id,
        ])->assertCreated()->assertJson(['status' => Collaborator::STATUS_APPROVED]);

        $this->assertDatabaseHas('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);
    }
}

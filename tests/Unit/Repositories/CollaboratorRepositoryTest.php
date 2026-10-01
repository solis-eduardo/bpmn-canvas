<?php

namespace Tests\Unit\Repositories;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\CollaboratorRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaboratorRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_a_collaborator_for_a_diagram_and_user(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        $repository = new CollaboratorRepository;

        $this->assertNull($repository->findFor($diagram, $user));

        $collaborator = $repository->upsertApproved($diagram, $user);

        $this->assertTrue($repository->findFor($diagram, $user)->is($collaborator));
        $this->assertTrue($repository->findForOrFail($diagram, $user)->is($collaborator));
    }

    public function test_find_for_or_fail_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new CollaboratorRepository)->findForOrFail(Diagram::factory()->create(), User::factory()->create());
    }

    public function test_upsert_status_creates_then_updates_the_same_row(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        $repository = new CollaboratorRepository;

        $requested = $repository->upsertStatus($diagram, $user, Collaborator::STATUS_REQUESTED);
        $this->assertTrue($requested->wasRecentlyCreated);

        $approved = $repository->upsertApproved($diagram, $user);

        $this->assertTrue($approved->is($requested));
        $this->assertSame(Collaborator::STATUS_APPROVED, $approved->status);
        $this->assertSame(1, Collaborator::count());
    }

    public function test_list_with_user_returns_only_that_diagrams_collaborators_with_users_loaded(): void
    {
        $diagram = Diagram::factory()->create();
        $repository = new CollaboratorRepository;
        $repository->upsertApproved($diagram, User::factory()->create());
        $repository->upsertApproved(Diagram::factory()->create(), User::factory()->create());

        $list = $repository->listWithUser($diagram);

        $this->assertCount(1, $list);
        $this->assertTrue($list->first()->relationLoaded('user'));
    }

    public function test_remove_for_deletes_and_returns_the_row_with_its_status(): void
    {
        $diagram = Diagram::factory()->create();
        $user = User::factory()->create();
        $repository = new CollaboratorRepository;
        $repository->upsertStatus($diagram, $user, Collaborator::STATUS_REQUESTED);

        $removed = $repository->removeFor($diagram, $user);

        $this->assertSame(Collaborator::STATUS_REQUESTED, $removed->status);
        $this->assertSame(0, Collaborator::count());
        $this->assertNull($repository->removeFor($diagram, $user));
    }
}

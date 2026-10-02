<?php

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Models\Workspace;
use App\Repositories\MemberRepository;
use App\Repositories\WorkspaceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): WorkspaceRepository
    {
        return new WorkspaceRepository(new MemberRepository);
    }

    public function test_finds_a_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $this->assertTrue($this->repository()->find($workspace->id)->is($workspace));
        $this->assertTrue($this->repository()->findOrFail($workspace->id)->is($workspace));
        $this->assertNull($this->repository()->find(0));
    }

    public function test_create_makes_the_owner_a_member(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->repository()->create('Finance', $owner);

        $this->assertSame('Finance', $workspace->name);
        $this->assertTrue($workspace->isOwnedBy($owner));
        $this->assertTrue($this->repository()->hasMember($workspace, $owner));
    }

    public function test_listings_are_ordered_by_name_and_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $repository = $this->repository();
        $zeta = $repository->create('Zeta', $user);
        $alpha = $repository->create('Alpha', $user);
        Workspace::factory()->create(['name' => 'Foreign']);

        $this->assertSame(['Alpha', 'Foreign', 'Zeta'], $repository->allOrderedByName()->pluck('name')->all());
        $this->assertSame([$alpha->id, $zeta->id], $repository->forUserOrderedByName($user)->pluck('id')->all());
    }

    public function test_listings_with_owner_and_members_eager_load_them(): void
    {
        $user = User::factory()->create();
        $repository = $this->repository();
        $repository->create('Alpha', $user);
        Workspace::factory()->create(['name' => 'Foreign']);

        $all = $repository->allWithOwnerAndMembers();
        $mine = $repository->forUserWithOwnerAndMembers($user);

        $this->assertCount(2, $all);
        $this->assertCount(1, $mine);
        foreach ([$all->first(), $mine->first()] as $workspace) {
            $this->assertTrue($workspace->relationLoaded('owner'));
            $this->assertTrue($workspace->relationLoaded('members'));
            $this->assertTrue($workspace->members->first()->relationLoaded('user'));
        }
    }

    public function test_add_and_remove_member(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $repository = $this->repository();

        $this->assertFalse($repository->hasMember($workspace, $user));
        $this->assertTrue($repository->addMember($workspace, $user)->wasRecentlyCreated);
        $this->assertFalse($repository->addMember($workspace, $user)->wasRecentlyCreated);
        $this->assertTrue($repository->hasMember($workspace, $user));

        $this->assertTrue($repository->removeMember($workspace, $user));
        $this->assertFalse($repository->removeMember($workspace, $user));
    }

    public function test_lists_workspaces_linked_to_any_of_the_keycloak_groups(): void
    {
        $finance = Workspace::factory()->create(['keycloak_group' => 'finance']);
        Workspace::factory()->create(['keycloak_group' => 'hr']);
        Workspace::factory()->create();

        $linked = $this->repository()->linkedToKeycloakGroups(['finance']);

        $this->assertSame([$finance->id], $linked->pluck('id')->all());
    }

    public function test_change_owner_sets_the_workspace_owner(): void
    {
        $workspace = Workspace::factory()->create();
        $newOwner = User::factory()->create();

        (new WorkspaceRepository(new MemberRepository))->changeOwner($workspace, $newOwner);

        $this->assertSame($newOwner->id, $workspace->fresh()->owner_id);
    }
}

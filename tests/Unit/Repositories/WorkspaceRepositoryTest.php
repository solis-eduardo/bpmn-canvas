<?php

namespace Tests\Unit\Repositories;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\MemberRepository;
use App\Repositories\WorkspaceListFilters;
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

    public function test_search_eager_loads_and_scopes_to_the_user_unless_admin_view(): void
    {
        $user = User::factory()->create();
        $repository = $this->repository();
        $repository->create('Alpha', $user);
        Workspace::factory()->create(['name' => 'Foreign']);

        $all = $repository->search($user, new WorkspaceListFilters, true)->getCollection();
        $mine = $repository->search($user, new WorkspaceListFilters, false)->getCollection();

        $this->assertCount(2, $all);
        $this->assertCount(1, $mine);
        $this->assertTrue($mine->first()->relationLoaded('owner'));
        $this->assertTrue($mine->first()->members->first()->relationLoaded('user'));
    }

    public function test_search_filters_by_name_owner_member_and_diagram(): void
    {
        $admin = User::factory()->create();
        $repository = $this->repository();
        $ann = User::factory()->create(['name' => 'Ann Owner', 'email' => 'ann@example.test']);
        $bob = User::factory()->create(['name' => 'Bob Member', 'email' => 'bob@example.test']);

        $finance = $repository->create('Finance', $ann);
        $repository->addMember($finance, $bob);
        Diagram::factory()->create(['workspace_id' => $finance->id, 'title' => 'Invoice approval', 'slug' => 'invoice-approval']);
        $hr = $repository->create('Human Resources', $bob);

        $names = fn (WorkspaceListFilters $f) => $repository->search($admin, $f, true)->pluck('name')->all();

        $this->assertSame(['Finance'], $names(new WorkspaceListFilters(name: 'fin')));
        $this->assertSame(['Finance'], $names(new WorkspaceListFilters(owner: 'ann@')));
        $this->assertSame(['Finance'], $names(new WorkspaceListFilters(owner: 'Ann')));
        $this->assertSame(['Finance', 'Human Resources'], $names(new WorkspaceListFilters(member: 'Bob')));
        $this->assertSame(['Finance'], $names(new WorkspaceListFilters(diagram: 'invoice')));
        $this->assertSame(['Finance'], $names(new WorkspaceListFilters(diagram: 'invoice-approval')));
        $this->assertSame([], $names(new WorkspaceListFilters(name: 'fin', owner: 'Bob')));
        $this->assertNotNull($hr);
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

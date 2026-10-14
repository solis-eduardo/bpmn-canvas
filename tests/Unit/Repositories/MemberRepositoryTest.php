<?php

namespace Tests\Unit\Repositories;

use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\MemberRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_manual_adds_a_member_once(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $repository = app(MemberRepository::class);

        $first = $repository->upsertManual($workspace, $user);
        $second = $repository->upsertManual($workspace, $user);

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame(1, Member::count());
        $this->assertTrue($repository->existsFor($workspace, $user));
        $this->assertTrue($repository->findFor($workspace, $user)->is($first));
    }

    public function test_upsert_manual_promotes_a_group_member_to_manual(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $repository = app(MemberRepository::class);
        $repository->createGroupIfMissing($workspace, $user);

        $member = $repository->upsertManual($workspace, $user);

        $this->assertSame(Member::ORIGIN_MANUAL, $member->origin);
    }

    public function test_create_group_if_missing_keeps_an_existing_manual_grant(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $repository = app(MemberRepository::class);
        $repository->upsertManual($workspace, $user);

        $member = $repository->createGroupIfMissing($workspace, $user);

        $this->assertSame(Member::ORIGIN_MANUAL, $member->origin);
        $this->assertSame(1, Member::count());
    }

    public function test_create_group_if_missing_creates_a_group_member(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();

        $member = app(MemberRepository::class)->createGroupIfMissing($workspace, $user);

        $this->assertSame(Member::ORIGIN_GROUP, $member->origin);
    }

    public function test_remove_for_reports_whether_a_member_was_removed(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $repository = app(MemberRepository::class);
        $repository->upsertManual($workspace, $user);

        $this->assertTrue($repository->removeFor($workspace, $user));
        $this->assertFalse($repository->removeFor($workspace, $user));
        $this->assertFalse($repository->existsFor($workspace, $user));
    }

    public function test_removes_only_group_memberships_outside_the_given_groups(): void
    {
        $user = User::factory()->create();
        $kept = Workspace::factory()->create(['keycloak_group' => 'finance']);
        $dropped = Workspace::factory()->create(['keycloak_group' => 'hr']);
        $manual = Workspace::factory()->create(['keycloak_group' => 'hr']);
        $repository = app(MemberRepository::class);
        $repository->createGroupIfMissing($kept, $user);
        $repository->createGroupIfMissing($dropped, $user);
        $repository->upsertManual($manual, $user);

        $removed = $repository->removeGroupMembershipsOutside($user, ['finance']);

        $this->assertSame(1, $removed);
        $this->assertTrue($repository->existsFor($kept, $user));
        $this->assertFalse($repository->existsFor($dropped, $user));
        $this->assertTrue($repository->existsFor($manual, $user));
    }
}

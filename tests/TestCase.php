<?php

namespace Tests;

use App\Models\User;
use App\Models\Workspace;
use App\Repositories\MemberRepository;
use App\Repositories\WorkspaceRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fixture helper: makes $user a (manual) Member without notifying them.
     */
    protected function addMember(Workspace $workspace, User $user): void
    {
        app(WorkspaceRepository::class)->addMember($workspace, $user);
    }

    protected function addGroupMember(Workspace $workspace, User $user): void
    {
        app(MemberRepository::class)->createGroupIfMissing($workspace, $user);
    }

    protected function removeMember(Workspace $workspace, User $user): void
    {
        app(WorkspaceRepository::class)->removeMember($workspace, $user);
    }

    protected function isMember(Workspace $workspace, User $user): bool
    {
        return app(WorkspaceRepository::class)->hasMember($workspace, $user);
    }
}

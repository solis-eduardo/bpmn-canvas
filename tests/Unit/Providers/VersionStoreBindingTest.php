<?php

namespace Tests\Unit\Providers;

use App\Repositories\DatabaseVersionRepository;
use App\Repositories\GitVersionRepository;
use App\Repositories\VersionRepository;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * VERSION_STORE picks the adapter behind the VersionRepository port (ADR-0018).
 */
class VersionStoreBindingTest extends TestCase
{
    public function test_git_is_the_store_when_configured(): void
    {
        config(['bpmn.version_store' => 'git']);

        $this->assertInstanceOf(GitVersionRepository::class, app(VersionRepository::class));
    }

    public function test_the_database_store_is_selected_when_configured(): void
    {
        config(['bpmn.version_store' => 'database']);

        $this->assertInstanceOf(DatabaseVersionRepository::class, app(VersionRepository::class));
    }

    public function test_an_unknown_store_is_rejected_instead_of_falling_back(): void
    {
        config(['bpmn.version_store' => 'filesystem']);

        $this->expectException(InvalidArgumentException::class);

        app(VersionRepository::class);
    }
}

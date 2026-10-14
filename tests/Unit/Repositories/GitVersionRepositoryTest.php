<?php

namespace Tests\Unit\Repositories;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\GitVersionRepository;
use App\Repositories\VersionRepository;

class GitVersionRepositoryTest extends VersionRepositoryContract
{
    protected function adapter(): VersionRepository
    {
        return new GitVersionRepository;
    }

    public function test_a_short_id_resolves_to_the_full_commit_id(): void
    {
        $author = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();
        $repository = $this->adapter();
        $id = $repository->list($diagram)[0]['id'];

        $this->assertSame($id, $repository->resolve($diagram, substr($id, 0, 7)));

        $repository->rename($diagram, substr($id, 0, 7), 'Milestone');

        $this->assertSame('Milestone', $repository->list($diagram)[0]['name']);
    }
}

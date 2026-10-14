<?php

namespace Tests\Unit\Repositories;

use App\Repositories\GitVersionRepository;
use App\Repositories\VersionRepository;

class GitVersionRepositoryTest extends VersionRepositoryContract
{
    protected function adapter(): VersionRepository
    {
        return new GitVersionRepository;
    }
}

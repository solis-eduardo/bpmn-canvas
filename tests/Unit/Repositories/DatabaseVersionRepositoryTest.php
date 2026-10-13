<?php

namespace Tests\Unit\Repositories;

use App\Repositories\Concerns\SharedVersionRepository;
use App\Repositories\DatabaseVersionRepository;
use App\Repositories\VersionRepository;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class DatabaseVersionRepositoryTest extends VersionRepositoryContract
{
    protected function adapter(): VersionRepository
    {
        return new DatabaseVersionRepository;
    }

    /**
     * The database adapter exists for the WebAssembly build, where there is no
     * git binary and no proc_open (ADR-0018), so it must never spawn a process.
     */
    #[Test]
    public function it_spawns_no_subprocesses(): void
    {
        foreach ([DatabaseVersionRepository::class, SharedVersionRepository::class] as $class) {
            $source = file_get_contents((new ReflectionClass($class))->getFileName());

            $this->assertStringNotContainsString('Symfony\\Component\\Process', $source, $class);
            $this->assertStringNotContainsString('ProcessRunner', $source, $class);
            $this->assertDoesNotMatchRegularExpression('/\b(proc_open|exec|shell_exec|passthru|system)\(/', $source, $class);
        }
    }
}

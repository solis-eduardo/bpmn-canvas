<?php

namespace Tests\Unit\Repositories;

use App\Models\Diagram;
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

    #[Test]
    public function it_takes_only_full_ids_so_a_short_id_resolves_to_nothing(): void
    {
        $diagram = Diagram::factory()->create();
        $id = $this->adapter()->list($diagram)[0]['id'];

        $this->assertNull($this->adapter()->resolve($diagram, substr($id, 0, 7)));
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

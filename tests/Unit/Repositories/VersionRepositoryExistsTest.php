<?php

namespace Tests\Unit\Repositories;

use App\Models\Diagram;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VersionRepositoryExistsTest extends TestCase
{
    use RefreshDatabase;

    public function test_exists_tells_a_real_version_from_anything_else(): void
    {
        Storage::fake('diagrams');
        $diagram = Diagram::factory()->create();
        $repository = app(VersionRepository::class);
        $id = $repository->list($diagram)[0]['id'];

        $this->assertTrue($repository->exists($diagram, $id));
        $this->assertFalse($repository->exists($diagram, str_repeat('a', 40)));
        $this->assertFalse($repository->exists($diagram, 'not-a-version'));
        $this->assertFalse($repository->exists($diagram, '--output=x'));
    }
}

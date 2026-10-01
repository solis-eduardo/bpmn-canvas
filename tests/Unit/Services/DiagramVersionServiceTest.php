<?php

namespace Tests\Unit\Services;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use App\Services\DiagramVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DiagramVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Diagram $diagram;

    /** @var list<string> Version ids, oldest first */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        $author = User::factory()->create();
        $this->diagram = Diagram::factory()->for($author, 'author')->create();
        $versions = app(VersionRepository::class);
        Storage::disk('diagrams')->put($this->diagram->disk_path, 'v2');
        $versions->save($this->diagram, $author, 'v2');

        $history = collect($versions->list($this->diagram))->sortBy('number')->values();
        $this->ids = $history->pluck('id')->all();
    }

    public function test_orders_the_two_versions_chronologically(): void
    {
        $result = app(DiagramVersionService::class)->compare($this->diagram, $this->ids[0], $this->ids[1]);

        $this->assertSame(1, $result['older']['number']);
        $this->assertNotSame('v2', $result['older']['content']);
        $this->assertSame(2, $result['newer']['number']);
        $this->assertSame('v2', $result['newer']['content']);
    }

    public function test_asking_in_reverse_order_gives_the_same_answer(): void
    {
        $service = app(DiagramVersionService::class);

        $this->assertSame(
            $service->compare($this->diagram, $this->ids[0], $this->ids[1]),
            $service->compare($this->diagram, $this->ids[1], $this->ids[0]),
        );
    }

    public function test_an_unknown_version_is_an_error(): void
    {
        $this->expectException(RuntimeException::class);

        app(DiagramVersionService::class)->compare($this->diagram, $this->ids[0], 'nope');
    }
}

<?php

namespace Tests\Unit\Services;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ForkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ForkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function origin(string $content = '<bpmn/>'): Diagram
    {
        $diagram = Diagram::factory()->create();
        Storage::disk('diagrams')->put($diagram->disk_path, $content);

        return $diagram;
    }

    public function test_the_fork_copies_the_content_and_belongs_to_the_forker(): void
    {
        $origin = $this->origin('<bpmn>copy me</bpmn>');
        $forker = User::factory()->create();

        $fork = app(ForkService::class)->create($origin, $forker);

        $this->assertNotSame($origin->slug, $fork->slug);
        $this->assertSame($forker->id, $fork->author_id);
        $this->assertSame($forker->id, $fork->owner_id);
        $this->assertSame($origin->id, $fork->forked_from_id);
        $this->assertNull($fork->workspace_id);
        $this->assertSame('<bpmn>copy me</bpmn>', Storage::disk('diagrams')->get($fork->disk_path));
    }

    public function test_the_fork_can_be_placed_in_a_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $fork = app(ForkService::class)->create($this->origin(), User::factory()->create(), $workspace);

        $this->assertSame($workspace->id, $fork->workspace_id);
    }

    public function test_the_fork_starts_its_own_version_history(): void
    {
        $fork = app(ForkService::class)->create($this->origin(), User::factory()->create());

        $this->assertDirectoryExists(Storage::disk('diagrams')->path($fork->slug.'/.git'));
    }

    public function test_an_unreadable_origin_registers_no_fork(): void
    {
        $origin = Diagram::factory()->create();
        Storage::disk('diagrams')->delete($origin->disk_path);
        $before = Diagram::count();

        try {
            app(ForkService::class)->create($origin, User::factory()->create());
            $this->fail('Expected the fork to fail.');
        } catch (RuntimeException) {
            $this->assertSame($before, Diagram::count());
        }
    }
}

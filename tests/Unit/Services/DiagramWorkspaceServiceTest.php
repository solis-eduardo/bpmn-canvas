<?php

namespace Tests\Unit\Services;

use App\Models\Diagram;
use App\Models\Workspace;
use App\Services\DiagramWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramWorkspaceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_puts_the_diagram_in_the_workspace(): void
    {
        $diagram = Diagram::factory()->create();
        $workspace = Workspace::factory()->create();

        app(DiagramWorkspaceService::class)->assign($diagram, $workspace);

        $this->assertSame($workspace->id, $diagram->fresh()->workspace_id);
    }

    public function test_assign_moves_it_from_one_workspace_to_another(): void
    {
        $diagram = Diagram::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);
        $target = Workspace::factory()->create();

        app(DiagramWorkspaceService::class)->assign($diagram, $target);

        $this->assertSame($target->id, $diagram->fresh()->workspace_id);
    }

    public function test_remove_takes_it_out_of_its_workspace(): void
    {
        $diagram = Diagram::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);

        app(DiagramWorkspaceService::class)->remove($diagram);

        $this->assertNull($diagram->fresh()->workspace_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiagramContentSearchTest extends TestCase
{
    use RefreshDatabase;

    private const BPMN_WITH_TASK = <<<'BPMN'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" name="Start" />
    <bpmn:task id="Task_1" name="Approve Purchase Order" />
    <bpmn:exclusiveGateway id="Gateway_1" name="Is Budget Available?" />
  </bpmn:process>
</bpmn:definitions>
BPMN;

    public function test_a_task_name_finds_the_diagram_it_appears_in(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Procurement']);
        Storage::disk('diagrams')->put($diagram->disk_path, self::BPMN_WITH_TASK);
        app(VersionRepository::class)->save($diagram, $user);

        $other = Diagram::factory()->for($user, 'author')->create(['title' => 'Something Else']);

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => 'Purchase Order']));

        $response->assertOk();
        $response->assertSee($diagram->title);
        $response->assertDontSee($other->title);
    }

    public function test_a_gateway_name_finds_the_diagram_it_appears_in(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Procurement']);
        Storage::disk('diagrams')->put($diagram->disk_path, self::BPMN_WITH_TASK);
        app(VersionRepository::class)->save($diagram, $user);

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => 'budget available']));

        $response->assertOk();
        $response->assertSee($diagram->title);
    }

    public function test_the_index_follows_a_save(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Procurement']);

        $this->actingAs($user)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => self::BPMN_WITH_TASK,
        ])->assertOk();

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => 'Approve Purchase Order']));

        $response->assertOk();
        $response->assertSee($diagram->title);
    }

    public function test_the_index_follows_a_restore(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Procurement']);
        $versions = app(VersionRepository::class);

        $versionId = $versions->save($diagram, $user, self::BPMN_WITH_TASK);
        $versions->save($diagram, $user, Diagram::emptyBpmn());

        // The task name is gone from the current content, so it shouldn't match.
        $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => 'Approve Purchase Order']))
            ->assertDontSee($diagram->title);

        $this->actingAs($user)->postJson("/diagrams/{$diagram->slug}/versions/{$versionId}/restore")->assertOk();

        // Restoring brings the task name back, so the index should follow.
        $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => 'Approve Purchase Order']))
            ->assertSee($diagram->title);
    }

    public function test_content_search_never_surfaces_a_hidden_diagram_the_user_cannot_see(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $hidden = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Hidden Procurement',
            'workspace_id' => $workspace->id,
        ]);
        Storage::disk('diagrams')->put($hidden->disk_path, self::BPMN_WITH_TASK);
        app(VersionRepository::class)->save($hidden, $owner);

        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get('/diagrams?'.http_build_query(['q' => 'Purchase Order']));

        $response->assertOk();
        $response->assertDontSee($hidden->title);
    }
}

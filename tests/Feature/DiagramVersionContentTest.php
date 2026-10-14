<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The BPMN content of one specific Version (used to compare it against the
 * current content on the canvas — ticket 35). Kept generic on purpose: the
 * endpoint returns a Version's content regardless of whether it's the
 * oldest, newest, or anything in between, since ticket 36 (comparing any two
 * Versions) reuses this same endpoint for both sides of the comparison.
 */
class DiagramVersionContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_it_returns_the_content_of_a_specific_version(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">first version</bpmn:definitions>',
        ])->assertOk();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">second version</bpmn:definitions>',
        ])->assertOk();

        $history = collect(
            $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        );

        // Oldest version is the one created by the factory, before either save above.
        $originalVersion = $history->last();

        $response = $this->actingAs($user)->getJson(
            '/diagrams/'.$diagram->slug.'/versions/'.$originalVersion['id'].'/content'
        );

        $response->assertOk();
        $response->assertJson([
            'id' => $originalVersion['id'],
        ]);
        $this->assertStringNotContainsString('first version', $response->json('content'));
        $this->assertStringNotContainsString('second version', $response->json('content'));
    }

    public function test_it_returns_the_content_of_the_current_version_too(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">latest content</bpmn:definitions>',
        ])->assertOk();

        $history = collect(
            $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        );

        $latestVersion = $history->first();

        $response = $this->actingAs($user)->getJson(
            '/diagrams/'.$diagram->slug.'/versions/'.$latestVersion['id'].'/content'
        );

        $response->assertOk();
        $this->assertSame('<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">latest content</bpmn:definitions>', $response->json('content'));
    }

    public function test_it_returns_404_for_an_unknown_version(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/deadbeef/content')
            ->assertNotFound();
    }

    public function test_it_returns_404_for_a_version_belonging_to_another_diagram(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $otherDiagram = Diagram::factory()->for($user, 'author')->create();

        // Distinct content, so the other Diagram's Version can't share a Git
        // hash with one in $diagram's own history by coincidence.
        $this->actingAs($user)->putJson('/diagrams/'.$otherDiagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">only in the other diagram</bpmn:definitions>',
        ])->assertOk();

        $foreignVersion = collect(
            $this->actingAs($user)->getJson('/diagrams/'.$otherDiagram->slug.'/versions')->json('versions')
        )->first();

        $this->actingAs($user)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/'.$foreignVersion['id'].'/content')
            ->assertNotFound();
    }

    public function test_a_collaborator_can_read_version_content(): void
    {
        $author = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $this->actingAs($author)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>',
        ])->assertOk();

        $history = collect(
            $this->actingAs($author)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        );

        $version = $history->first();

        $this->actingAs($collaborator)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/'.$version['id'].'/content')
            ->assertOk();
    }

    public function test_a_user_without_edit_permission_cannot_read_version_content(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $this->actingAs($author)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>',
        ])->assertOk();

        $history = collect(
            $this->actingAs($author)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        );

        $version = $history->first();

        $this->actingAs($outsider)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/'.$version['id'].'/content')
            ->assertForbidden();
    }

    public function test_an_administrator_can_read_version_content_without_edit_rights(): void
    {
        $author = User::factory()->create();
        $administrator = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $this->actingAs($author)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>',
        ])->assertOk();

        $history = collect(
            $this->actingAs($author)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        );

        $version = $history->first();

        $this->actingAs($administrator)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/'.$version['id'].'/content')
            ->assertOk();
    }

    public function test_no_api_response_exposes_git_vocabulary(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>',
        ])->assertOk();

        $history = collect(
            $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        );

        $version = $history->first();

        $response = $this->actingAs($user)->getJson(
            '/diagrams/'.$diagram->slug.'/versions/'.$version['id'].'/content'
        );

        $response->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsStringIgnoringCase('commit', $body);
        $this->assertStringNotContainsStringIgnoringCase('branch', $body);
        $this->assertStringNotContainsStringIgnoringCase('git', $body);
    }
}

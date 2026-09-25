<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiagramVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_creating_a_diagram_generates_an_initial_version(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/diagrams', ['title' => 'Versioned Process'])->assertRedirect();

        $diagram = Diagram::where('slug', 'versioned-process')->firstOrFail();

        $response = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions');

        $response->assertOk();
        $this->assertCount(1, $response->json('versions.0.items'));
    }

    public function test_multiple_saves_generate_multiple_versions_without_discarding_any(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>version one</bpmn:definitions>',
        ])->assertOk();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>version two</bpmn:definitions>',
        ])->assertOk();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>version three</bpmn:definitions>',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions');

        $response->assertOk();
        $items = collect($response->json('versions'))->flatMap(fn ($day) => $day['items']);

        // Initial version (created via factory, which also generates a version) + 3 saves.
        $this->assertCount(4, $items);
    }

    public function test_saving_identical_content_does_not_create_a_new_version(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>same content</bpmn:definitions>',
        ])->assertOk();

        $before = collect(
            $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        )->flatMap(fn ($day) => $day['items']);

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>same content</bpmn:definitions>',
        ])->assertOk();

        $after = collect(
            $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        )->flatMap(fn ($day) => $day['items']);

        $this->assertCount($before->count(), $after);
    }

    public function test_version_history_is_grouped_by_calendar_day(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        $diagram = Diagram::factory()->for($user, 'author')->create();

        Carbon::setTestNow(Carbon::parse('2026-09-20 11:00:00'));
        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>day 1 - first</bpmn:definitions>',
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-09-21 09:00:00'));
        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>day 2</bpmn:definitions>',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions');
        Carbon::setTestNow();

        $response->assertOk();
        $days = collect($response->json('versions'))->pluck('day')->all();

        $this->assertContains('2026-09-20', $days);
        $this->assertContains('2026-09-21', $days);

        $expectedDay = collect($response->json('versions'))->firstWhere('day', '2026-09-20');
        $this->assertCount(2, $expectedDay['items']);
    }

    public function test_restoring_an_old_version_makes_content_match_it_and_creates_a_new_version_on_top(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $originalContent = Storage::disk('diagrams')->get($diagram->disk_path);

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>intermediate version</bpmn:definitions>',
        ])->assertOk();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>most recent version</bpmn:definitions>',
        ])->assertOk();

        $history = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions');
        $allVersions = collect($history)->flatMap(fn ($day) => $day['items']);

        // The oldest version (original content from creation) is the last item.
        $originalVersion = $allVersions->last();
        $versionCountBeforeRestore = $allVersions->count();

        $restore = $this->actingAs($user)->postJson(
            '/diagrams/'.$diagram->slug.'/versions/'.$originalVersion['id'].'/restore'
        );
        $restore->assertOk();

        $currentContent = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/content')->json('content');
        $this->assertSame($originalContent, $currentContent);

        $historyAfter = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions');
        $allVersionsAfter = collect($historyAfter)->flatMap(fn ($day) => $day['items']);

        // A new version was created on top of the history.
        $this->assertSame($versionCountBeforeRestore + 1, $allVersionsAfter->count());

        // The intermediate history still exists/is visible (nothing was deleted).
        $idsAfter = $allVersionsAfter->pluck('id')->all();
        foreach ($allVersions->pluck('id') as $oldId) {
            $this->assertContains($oldId, $idsAfter);
        }
    }

    public function test_no_api_response_exposes_git_vocabulary(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>another version</bpmn:definitions>',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions');
        $response->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsStringIgnoringCase('commit', $body);
        $this->assertStringNotContainsStringIgnoringCase('branch', $body);
        $this->assertStringNotContainsStringIgnoringCase('git', $body);
    }

    public function test_a_user_without_permission_cannot_view_or_restore_versions(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $this->actingAs($outsider)->getJson('/diagrams/'.$diagram->slug.'/versions')->assertForbidden();
        $this->actingAs($outsider)->postJson('/diagrams/'.$diagram->slug.'/versions/deadbeef/restore')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Comparing any two Versions of a Diagram's history (ticket 36), not just an
 * old Version against the current canvas content (ticket 35). Reuses the
 * same "get a Version's content" building block (VersionRepository::get)
 * that endpoint already relies on — this endpoint just resolves BOTH ends
 * in one request and figures out which one is chronologically older, so the
 * canvas always diffs "older -> newer" regardless of the order the User
 * picked them in.
 */
class DiagramCompareVersionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function history(User $user, Diagram $diagram)
    {
        return collect(
            $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        )->flatMap(fn ($day) => $day['items']);
    }

    public function test_it_compares_two_arbitrary_non_adjacent_versions(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        foreach (['v1', 'v2', 'v3'] as $content) {
            $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
                'content' => '<bpmn:definitions>'.$content.'</bpmn:definitions>',
            ])->assertOk();
        }

        // 4 versions total: the factory's initial one, plus v1, v2, v3.
        $history = $this->history($user, $diagram)->sortBy('number')->values();
        $this->assertCount(4, $history);

        $oldest = $history->first(); // the factory's initial version
        $thirdSaved = $history->get(2); // "v2"

        $response = $this->actingAs($user)->getJson(
            '/diagrams/'.$diagram->slug.'/versions/compare?from='.$oldest['id'].'&to='.$thirdSaved['id']
        );

        $response->assertOk();
        $response->assertJson([
            'older' => ['id' => $oldest['id'], 'number' => $oldest['number']],
            'newer' => ['id' => $thirdSaved['id'], 'number' => $thirdSaved['number']],
        ]);
        $this->assertStringContainsString('v2', $response->json('newer.content'));
        $this->assertStringNotContainsString('v2', $response->json('older.content'));
    }

    public function test_the_result_does_not_depend_on_the_order_the_user_picked_the_two_versions_in(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>second</bpmn:definitions>',
        ])->assertOk();

        $history = $this->history($user, $diagram)->sortBy('number')->values();
        $older = $history->first();
        $newer = $history->last();

        $forward = $this->actingAs($user)->getJson(
            '/diagrams/'.$diagram->slug.'/versions/compare?from='.$older['id'].'&to='.$newer['id']
        )->json();

        $backward = $this->actingAs($user)->getJson(
            '/diagrams/'.$diagram->slug.'/versions/compare?from='.$newer['id'].'&to='.$older['id']
        )->json();

        $this->assertSame($forward, $backward);
        $this->assertSame($older['id'], $forward['older']['id']);
        $this->assertSame($newer['id'], $forward['newer']['id']);
    }

    public function test_it_returns_404_when_one_version_is_unknown(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $history = $this->history($user, $diagram);
        $known = $history->first();

        $this->actingAs($user)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/compare?from='.$known['id'].'&to=deadbeef')
            ->assertNotFound();
    }

    public function test_it_returns_404_for_a_version_belonging_to_another_diagram(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $otherDiagram = Diagram::factory()->for($user, 'author')->create();

        $history = $this->history($user, $diagram);
        $known = $history->first();

        // Distinct content, so the other Diagram's Version has a Git hash
        // that can't coincidentally also exist in $diagram's own history
        // (two brand-new Diagrams' identical empty initial content, saved
        // within the same second by the same author, can otherwise hash to
        // the same commit id — that's a Git implementation detail, not a
        // cross-Diagram leak, since VersionRepository::get() always reads
        // from the given Diagram's own repository).
        $this->actingAs($user)->putJson('/diagrams/'.$otherDiagram->slug.'/content', [
            'content' => '<bpmn:definitions>only in the other diagram</bpmn:definitions>',
        ])->assertOk();

        $otherHistory = $this->history($user, $otherDiagram);
        $foreignVersion = $otherHistory->first();

        $this->actingAs($user)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/compare?from='.$known['id'].'&to='.$foreignVersion['id'])
            ->assertNotFound();
    }

    public function test_it_requires_both_version_parameters(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $history = $this->history($user, $diagram);
        $known = $history->first();

        $this->actingAs($user)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/compare?from='.$known['id'])
            ->assertStatus(422);
    }

    public function test_a_collaborator_can_compare_two_versions(): void
    {
        $author = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        DiagramCollaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);

        $this->actingAs($author)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>content</bpmn:definitions>',
        ])->assertOk();

        $history = $this->history($author, $diagram)->sortBy('number')->values();

        $this->actingAs($collaborator)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/compare?from='.$history->first()['id'].'&to='.$history->last()['id'])
            ->assertOk();
    }

    public function test_a_user_without_edit_permission_cannot_compare_versions(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $this->actingAs($author)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>content</bpmn:definitions>',
        ])->assertOk();

        $history = $this->history($author, $diagram)->sortBy('number')->values();

        $this->actingAs($outsider)
            ->getJson('/diagrams/'.$diagram->slug.'/versions/compare?from='.$history->first()['id'].'&to='.$history->last()['id'])
            ->assertForbidden();
    }

    public function test_no_api_response_exposes_git_vocabulary(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions>content</bpmn:definitions>',
        ])->assertOk();

        $history = $this->history($user, $diagram)->sortBy('number')->values();

        $response = $this->actingAs($user)->getJson(
            '/diagrams/'.$diagram->slug.'/versions/compare?from='.$history->first()['id'].'&to='.$history->last()['id']
        );

        $response->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsStringIgnoringCase('commit', $body);
        $this->assertStringNotContainsStringIgnoringCase('branch', $body);
        $this->assertStringNotContainsStringIgnoringCase('git', $body);
    }
}

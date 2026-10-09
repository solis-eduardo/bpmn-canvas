<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Provisional Versions as an explicit state (not a localized name): badge
 * data in the history, make permanent, delete (soft: hidden from the
 * history, Git commit and numbering kept) and who may do so.
 */
class ProvisionalVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function save(User $user, Diagram $diagram, string $label): void
    {
        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">'.$label.'</bpmn:definitions>',
        ])->assertOk();
    }

    /**
     * A Diagram with Version 1, a provisional Version 2 and Version 3 saved
     * afterwards, so the provisional one is not the current Version.
     *
     * @return array{0: User, 1: Diagram, 2: string}
     */
    private function diagramWithProvisional(): array
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $versions = app(VersionRepository::class);

        $this->save($user, $diagram, 'draft');

        $provisionalId = $versions->list($diagram)[0]['id'];
        $versions->markProvisional($diagram, $provisionalId);

        $this->save($user, $diagram, 'later');

        return [$user, $diagram, $provisionalId];
    }

    private function items(User $user, Diagram $diagram): Collection
    {
        return collect(
            $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions')
        )->flatMap(fn ($day) => $day['items']);
    }

    public function test_the_history_flags_provisional_versions_even_after_a_rename(): void
    {
        [$user, $diagram, $provisionalId] = $this->diagramWithProvisional();

        $this->actingAs($user)->putJson(
            '/diagrams/'.$diagram->slug.'/versions/'.$provisionalId.'/rename',
            ['name' => 'Renamed draft']
        )->assertOk();

        $items = $this->items($user, $diagram);

        $this->assertSame([$provisionalId], $items->where('provisional', true)->pluck('id')->all());
        $this->assertSame('Renamed draft', $items->firstWhere('id', $provisionalId)['name']);
    }

    public function test_an_unnamed_provisional_version_uses_the_localized_label(): void
    {
        [$user, $diagram, $provisionalId] = $this->diagramWithProvisional();

        foreach (['en', 'pt_BR', 'es'] as $locale) {
            app()->setLocale($locale);

            $name = $this->items($user, $diagram)->firstWhere('id', $provisionalId)['name'];

            $this->assertSame(__('messages.provisional_version'), $name);
        }
    }

    public function test_an_editor_can_make_a_provisional_version_permanent(): void
    {
        [$user, $diagram, $provisionalId] = $this->diagramWithProvisional();

        $this->actingAs($user)
            ->postJson('/diagrams/'.$diagram->slug.'/versions/'.$provisionalId.'/promote')
            ->assertOk();

        $item = $this->items($user, $diagram)->firstWhere('id', $provisionalId);

        $this->assertFalse($item['provisional']);
        $this->assertSame('Version 2', $item['name']);
    }

    public function test_an_editor_can_delete_a_provisional_version_and_numbering_stays_stable(): void
    {
        [$user, $diagram, $provisionalId] = $this->diagramWithProvisional();

        $this->actingAs($user)
            ->deleteJson('/diagrams/'.$diagram->slug.'/versions/'.$provisionalId)
            ->assertOk();

        $items = $this->items($user, $diagram);

        $this->assertNotContains($provisionalId, $items->pluck('id')->all());
        $this->assertSame([3, 1], $items->pluck('number')->all());

        // Soft delete: the Version is still resolvable (Shares, comparisons).
        $all = collect(app(VersionRepository::class)->list($diagram))->keyBy('id');
        $this->assertTrue($all[$provisionalId]['hidden']);
        $this->assertSame(2, $all[$provisionalId]['number']);
        $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions/'.$provisionalId.'/content')->assertOk();
    }

    public function test_only_provisional_versions_can_be_promoted_or_deleted(): void
    {
        [$user, $diagram] = $this->diagramWithProvisional();
        $ordinaryId = $this->items($user, $diagram)->last()['id'];

        $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/versions/'.$ordinaryId.'/promote')->assertStatus(409);
        $this->actingAs($user)->deleteJson('/diagrams/'.$diagram->slug.'/versions/'.$ordinaryId)->assertStatus(409);

        $this->assertCount(3, $this->items($user, $diagram));
    }

    public function test_an_unknown_version_is_not_found(): void
    {
        [$user, $diagram] = $this->diagramWithProvisional();
        $unknown = str_repeat('a', 40);

        $this->actingAs($user)->postJson('/diagrams/'.$diagram->slug.'/versions/'.$unknown.'/promote')->assertNotFound();
        $this->actingAs($user)->deleteJson('/diagrams/'.$diagram->slug.'/versions/'.$unknown)->assertNotFound();
    }

    public function test_a_user_who_cannot_edit_cannot_promote_or_delete(): void
    {
        [$owner, $diagram, $provisionalId] = $this->diagramWithProvisional();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson('/diagrams/'.$diagram->slug.'/versions/'.$provisionalId.'/promote')->assertForbidden();
        $this->actingAs($outsider)->deleteJson('/diagrams/'.$diagram->slug.'/versions/'.$provisionalId)->assertForbidden();

        $this->assertTrue($this->items($owner, $diagram)->firstWhere('id', $provisionalId)['provisional']);
    }

    public function test_deleting_the_current_version_keeps_the_current_marker_off_the_list(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $versions = app(VersionRepository::class);

        $this->save($user, $diagram, 'draft');
        $currentId = $versions->list($diagram)[0]['id'];
        $versions->markProvisional($diagram, $currentId);

        $this->actingAs($user)->deleteJson('/diagrams/'.$diagram->slug.'/versions/'.$currentId)->assertOk();

        $this->assertSame([false], $this->items($user, $diagram)->pluck('is_current')->all());
    }
}

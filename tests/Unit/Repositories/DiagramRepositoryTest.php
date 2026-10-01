<?php

namespace Tests\Unit\Repositories;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\DiagramOldSlug;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\DiagramListCriteria;
use App\Repositories\DiagramRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DiagramRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DiagramRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DiagramRepository;
    }

    public function test_finds_by_id_and_slug(): void
    {
        $diagram = Diagram::factory()->create();

        $this->assertTrue($this->repository->find($diagram->id)->is($diagram));
        $this->assertNull($this->repository->find(0));
        $this->assertTrue($this->repository->findBySlugOrFail($diagram->slug)->is($diagram));
    }

    public function test_find_by_slug_or_fail_throws_for_an_unknown_slug(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findBySlugOrFail('nope');
    }

    public function test_find_by_slug_or_old_slug_flags_which_one_matched(): void
    {
        $diagram = Diagram::factory()->create(['slug' => 'current']);
        DiagramOldSlug::create(['diagram_id' => $diagram->id, 'old_slug' => 'former']);

        $byCurrent = $this->repository->findBySlugOrOldSlug('current');
        $byOld = $this->repository->findBySlugOrOldSlug('former');

        $this->assertTrue($byCurrent->diagram->is($diagram));
        $this->assertFalse($byCurrent->viaOldSlug);
        $this->assertTrue($byOld->diagram->is($diagram));
        $this->assertTrue($byOld->viaOldSlug);
        $this->assertNull($this->repository->findBySlugOrOldSlug('missing'));
    }

    public function test_unique_slug_is_the_plain_slug_when_free(): void
    {
        $this->assertSame('my-process', $this->repository->uniqueSlug('My Process'));
    }

    public function test_unique_slug_adds_a_suffix_on_collision_with_a_current_slug(): void
    {
        Diagram::factory()->create(['slug' => 'my-process']);

        $slug = $this->repository->uniqueSlug('My Process');

        $this->assertMatchesRegularExpression('/^my-process-[a-z0-9]{4}$/', $slug);
    }

    public function test_unique_slug_adds_a_suffix_on_collision_with_a_reserved_old_slug(): void
    {
        $diagram = Diagram::factory()->create();
        DiagramOldSlug::create(['diagram_id' => $diagram->id, 'old_slug' => 'my-process']);

        $slug = $this->repository->uniqueSlug('My Process');

        $this->assertMatchesRegularExpression('/^my-process-[a-z0-9]{4}$/', $slug);
    }

    public function test_unique_slug_ignores_the_diagram_being_renamed(): void
    {
        $diagram = Diagram::factory()->create(['slug' => 'my-process']);

        $this->assertSame('my-process', $this->repository->uniqueSlug('My Process', $diagram->id));
    }

    public function test_unique_slug_falls_back_to_a_long_suffix_when_short_ones_keep_colliding(): void
    {
        Diagram::factory()->create(['slug' => 'my-process']);
        Diagram::factory()->create(['slug' => 'my-process-aaaa']);
        Str::createRandomStringsUsing(fn (int $length) => str_repeat('a', $length));

        try {
            $slug = $this->repository->uniqueSlug('My Process');
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame('my-process-aaaaaaaaaaaa', $slug);
    }

    public function test_recent_for_returns_the_users_most_recent_editable_diagrams(): void
    {
        $user = User::factory()->create();
        $old = Diagram::factory()->create(['owner_id' => $user->id, 'updated_at' => now()->subDays(3)]);
        $new = Diagram::factory()->create(['owner_id' => $user->id, 'updated_at' => now()]);
        Diagram::factory()->create(['updated_at' => now()]);

        $this->assertSame([$new->id, $old->id], $this->repository->recentFor($user)->pluck('id')->all());
        $this->assertCount(1, $this->repository->recentFor($user, 1));
    }

    public function test_recent_for_an_admin_includes_every_diagram(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Diagram::factory()->count(2)->create();

        $this->assertCount(2, $this->repository->recentFor($admin));
    }

    public function test_visible_list_query_hides_diagrams_the_user_cannot_open(): void
    {
        $user = User::factory()->create();
        $mine = Diagram::factory()->create(['owner_id' => $user->id]);
        Diagram::factory()->create();

        $ids = $this->repository->visibleListQuery($user, new DiagramListCriteria)->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_visible_list_query_narrows_by_the_criteria(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->create(['user_id' => $user->id, 'origin' => 'manual']);
        $plain = Diagram::factory()->create(['owner_id' => $user->id, 'title' => 'Plain']);
        $inWorkspace = Diagram::factory()->create(['owner_id' => $user->id, 'workspace_id' => $workspace->id, 'title' => 'Billing']);
        $locked = Diagram::factory()->create(['owner_id' => $user->id, 'locked_by' => $user->id]);
        $tagged = Diagram::factory()->create(['owner_id' => $user->id]);
        $tagged->tags()->attach(Tag::create(['name' => 'hr']));

        $ids = fn (DiagramListCriteria $criteria) => $this->repository->visibleListQuery($user, $criteria)->pluck('id')->all();

        $this->assertSame([$inWorkspace->id], $ids(new DiagramListCriteria(search: 'bill', workspaceId: $workspace->id)));
        $this->assertEqualsCanonicalizing([$plain->id, $locked->id, $tagged->id], $ids(new DiagramListCriteria(withoutWorkspace: true)));
        $this->assertSame([$locked->id], $ids(new DiagramListCriteria(locked: true)));
        $this->assertSame([$tagged->id], $ids(new DiagramListCriteria(tag: 'hr')));
        $this->assertCount(4, $ids(new DiagramListCriteria(recent: true)));
    }

    public function test_visible_list_query_treats_owner_and_collaborator_as_a_union(): void
    {
        $user = User::factory()->create();
        $owned = Diagram::factory()->create(['owner_id' => $user->id]);
        $collaborated = Diagram::factory()->create();
        Collaborator::create(['diagram_id' => $collaborated->id, 'user_id' => $user->id, 'status' => Collaborator::STATUS_APPROVED]);

        $both = $this->repository->visibleListQuery($user, new DiagramListCriteria(owner: true, collaborator: true))->pluck('id')->all();
        $onlyOwner = $this->repository->visibleListQuery($user, new DiagramListCriteria(owner: true))->pluck('id')->all();
        $onlyCollaborator = $this->repository->visibleListQuery($user, new DiagramListCriteria(collaborator: true))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$owned->id, $collaborated->id], $both);
        $this->assertSame([$owned->id], $onlyOwner);
        $this->assertSame([$collaborated->id], $onlyCollaborator);
    }

    public function test_discovery_query_lists_only_openable_out_of_workspace_diagrams_of_others(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->create(['owner_id' => $user->id]);
        $approved = Diagram::factory()->create();
        Collaborator::create(['diagram_id' => $approved->id, 'user_id' => $user->id, 'status' => Collaborator::STATUS_APPROVED]);
        $requested = Diagram::factory()->create(['title' => 'Requested one']);
        Collaborator::create(['diagram_id' => $requested->id, 'user_id' => $user->id, 'status' => Collaborator::STATUS_REQUESTED]);
        $open = Diagram::factory()->create(['title' => 'Open one']);
        $workspace = Workspace::factory()->create();
        Diagram::factory()->create(['workspace_id' => $workspace->id]);

        $this->assertEqualsCanonicalizing([$requested->id, $open->id], $this->repository->discoveryQuery($user)->pluck('id')->all());
        $this->assertSame([$open->id], $this->repository->discoveryQuery($user, 'open')->pluck('id')->all());
    }

    public function test_discovery_query_is_empty_for_an_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Diagram::factory()->create();

        $this->assertCount(0, $this->repository->discoveryQuery($admin)->get());
    }

    public function test_criteria_are_read_from_the_query_string(): void
    {
        $request = Request::create('/diagrams', 'GET', ['q' => ' bill ', 'tag' => 'hr', 'workspace' => '7', 'owner' => '1', 'recent' => '1']);

        $criteria = DiagramListCriteria::fromRequest($request);

        $this->assertEquals(new DiagramListCriteria(search: 'bill', tag: 'hr', workspaceId: 7, owner: true, recent: true), $criteria);
        $this->assertTrue(DiagramListCriteria::fromRequest(Request::create('/diagrams', 'GET', ['workspace' => 'none']))->withoutWorkspace);
        $this->assertNull(DiagramListCriteria::fromRequest(Request::create('/diagrams', 'GET', ['workspace' => 'all']))->workspaceId);
    }

    public function test_content_of_reads_the_file_and_fails_loudly_when_missing(): void
    {
        Storage::fake('diagrams');
        $diagram = Diagram::factory()->create();
        Storage::disk('diagrams')->put($diagram->disk_path, '<bpmn/>');

        $this->assertSame('<bpmn/>', $this->repository->contentOf($diagram));

        Storage::disk('diagrams')->delete($diagram->disk_path);
        $this->expectException(RuntimeException::class);

        $this->repository->contentOf($diagram);
    }

    public function test_change_owner_leaves_the_author_alone(): void
    {
        $diagram = Diagram::factory()->create();
        $author = $diagram->author_id;
        $newOwner = User::factory()->create();

        $this->repository->changeOwner($diagram, $newOwner);

        $this->assertSame($newOwner->id, $diagram->fresh()->owner_id);
        $this->assertSame($author, $diagram->fresh()->author_id);
    }

    public function test_place_in_workspace_sets_and_clears_the_workspace(): void
    {
        $diagram = Diagram::factory()->create();
        $workspace = Workspace::factory()->create();

        $this->repository->placeInWorkspace($diagram, $workspace);
        $this->assertSame($workspace->id, $diagram->fresh()->workspace_id);

        $this->repository->placeInWorkspace($diagram, null);
        $this->assertNull($diagram->fresh()->workspace_id);
    }
}

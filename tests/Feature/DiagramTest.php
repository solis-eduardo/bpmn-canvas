<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramOldSlug;
use App\Models\Share;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiagramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_authenticated_user_can_create_a_diagram_with_a_slug_derived_from_the_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/diagrams', [
            'title' => 'Purchase Process',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('diagrams', [
            'title' => 'Purchase Process',
            'slug' => 'purchase-process',
            'author_id' => $user->id,
        ]);

        $diagram = Diagram::where('slug', 'purchase-process')->firstOrFail();
        Storage::disk('diagrams')->assertExists($diagram->disk_path);
    }

    public function test_creating_a_diagram_under_a_non_english_locale_localizes_the_start_event_label(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'pt_BR'])
            ->post('/diagrams', ['title' => 'Processo de Compra']);

        $response->assertRedirect();

        $diagram = Diagram::where('slug', 'processo-de-compra')->firstOrFail();
        $content = Storage::disk('diagrams')->get($diagram->disk_path);

        $this->assertStringContainsString('<bpmn:startEvent id="StartEvent_1" name="Início" />', $content);
        $this->assertStringNotContainsString('name="Start"', $content);
    }

    public function test_guest_cannot_create_a_diagram(): void
    {
        $response = $this->post('/diagrams', ['title' => 'No Login']);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('diagrams', ['title' => 'No Login']);
    }

    public function test_listing_hides_other_users_diagrams_unless_searched(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Diagram::factory()->for($other, 'author')->create(['title' => "Another User's Diagram"]);

        $this->actingAs($user)->get('/diagrams')->assertOk()->assertDontSee("Another User's Diagram");
        $this->actingAs($user)->get('/diagrams?q=Another')->assertOk()->assertSee("Another User's Diagram");
    }

    public function test_diagram_listing_shows_the_last_modification_time(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create([
            'updated_at' => now()->subHours(3),
        ]);

        $response = $this->actingAs($user)->get('/diagrams');

        $response->assertOk();
        $response->assertSee($diagram->updated_at->diffForHumans());
    }

    public function test_user_can_open_the_editor_of_an_existing_diagram_by_its_current_slug(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertSee($diagram->title);
        // The Editor shares the header of the other screens.
        $response->assertSee('mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6', false);
        $response->assertDontSee('flex w-full flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:flex-nowrap', false);
        // The Editor chrome comes from the Vite-built, layered stylesheet, not the legacy unlayered one.
        $response->assertDontSee('assets/app.css', false);
        $response->assertSee('build/assets/editor-', false);
        $response->assertSee('flex flex-1 items-center gap-1', false);
    }

    public function test_user_can_fetch_the_current_content_of_a_diagram(): void
    {
        // Ensure the locale is set to 'en' before creating the diagram so the
        // factory-generated BPMN content uses the English label. This test must
        // pass regardless of what locale other tests may have set previously.
        $this->app->setLocale('en');

        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/content');

        $response->assertOk();
        $response->assertJson(['content' => Diagram::emptyBpmn()]);
    }

    public function test_saving_a_diagram_overwrites_the_content_on_disk(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $newContent = '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">new content</bpmn:definitions>';

        $response = $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => $newContent,
        ]);

        $response->assertOk();
        Storage::disk('diagrams')->assertExists($diagram->disk_path);
        $this->assertSame($newContent, Storage::disk('diagrams')->get($diagram->disk_path));
    }

    public function test_renaming_a_diagram_updates_slug_and_the_old_slug_keeps_redirecting(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Original Name']);
        $originalSlug = $diagram->slug;

        $response = $this->actingAs($user)->putJson('/diagrams/'.$originalSlug, [
            'title' => 'New Name',
        ]);

        $response->assertOk();
        $diagram->refresh();
        $this->assertSame('new-name', $diagram->slug);
        $this->assertSame('New Name', $diagram->title);

        $this->assertDatabaseHas('diagram_old_slugs', [
            'diagram_id' => $diagram->id,
            'old_slug' => $originalSlug,
        ]);

        Storage::disk('diagrams')->assertMissing($originalSlug.'/diagram.bpmn');
        Storage::disk('diagrams')->assertExists($diagram->disk_path);

        $redirect = $this->actingAs($user)->get('/diagrams/'.$originalSlug);
        $redirect->assertRedirect('/diagrams/new-name');
    }

    public function test_old_slug_still_redirects_after_multiple_renames_in_sequence(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'First Name']);
        $firstSlug = $diagram->slug;

        $this->actingAs($user)->putJson('/diagrams/'.$firstSlug, ['title' => 'Second Name'])->assertOk();
        $diagram->refresh();
        $secondSlug = $diagram->slug;

        $this->actingAs($user)->putJson('/diagrams/'.$secondSlug, ['title' => 'Third Name'])->assertOk();
        $diagram->refresh();

        $this->actingAs($user)->get('/diagrams/'.$firstSlug)
            ->assertRedirect('/diagrams/'.$diagram->slug);

        $this->actingAs($user)->get('/diagrams/'.$secondSlug)
            ->assertRedirect('/diagrams/'.$diagram->slug);
    }

    public function test_an_old_slug_is_never_reused_by_a_different_diagram(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Old Name']);
        $oldSlug = $diagram->slug;

        $this->actingAs($user)->putJson('/diagrams/'.$oldSlug, ['title' => 'New Name'])->assertOk();

        $response = $this->actingAs($user)->post('/diagrams', ['title' => 'Old Name']);
        $response->assertRedirect();

        $newDiagram = Diagram::where('title', 'Old Name')->firstOrFail();
        $this->assertNotSame($oldSlug, $newDiagram->slug);
    }

    public function test_creating_a_diagram_with_a_colliding_slug_automatically_gets_a_suffix(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->create(['title' => 'Standard Process', 'slug' => 'standard-process']);

        $response = $this->actingAs($user)->post('/diagrams', ['title' => 'Standard Process']);
        $response->assertRedirect();

        $diagrams = Diagram::where('title', 'Standard Process')->get();
        $this->assertCount(2, $diagrams);
        $slugs = $diagrams->pluck('slug')->all();
        $this->assertContains('standard-process', $slugs);
        $this->assertNotEquals(['standard-process', 'standard-process'], $slugs);
    }

    public function test_renaming_a_diagram_to_a_slug_reserved_as_someone_elses_old_slug_gets_a_suffix(): void
    {
        $user = User::factory()->create();

        $diagramA = Diagram::factory()->for($user, 'author')->create(['title' => 'Reserved Name', 'slug' => 'reserved-name']);
        $this->actingAs($user)->putJson('/diagrams/'.$diagramA->slug, ['title' => 'Another Name'])->assertOk();
        $diagramA->refresh();
        $this->assertDatabaseHas('diagram_old_slugs', ['old_slug' => 'reserved-name']);

        $diagramB = Diagram::factory()->for($user, 'author')->create(['title' => 'Diagram B']);

        $response = $this->actingAs($user)->putJson('/diagrams/'.$diagramB->slug, ['title' => 'Reserved Name']);
        $response->assertOk();

        $diagramB->refresh();
        $this->assertNotSame('reserved-name', $diagramB->slug);
        $this->assertSame('Reserved Name', $diagramB->title);
    }

    public function test_deleting_a_diagram_removes_access_via_editor_and_any_associated_slug(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'To Delete']);
        $currentSlug = $diagram->slug;

        $this->actingAs($user)->putJson('/diagrams/'.$currentSlug, ['title' => 'To Delete Renamed'])->assertOk();
        $diagram->refresh();
        $renamedSlug = $diagram->slug;

        $this->actingAs($user)->deleteJson('/diagrams/'.$renamedSlug)->assertOk();

        $this->assertDatabaseMissing('diagrams', ['id' => $diagram->id]);
        $this->assertDatabaseMissing('diagram_old_slugs', ['diagram_id' => $diagram->id]);

        $this->actingAs($user)->get('/diagrams/'.$renamedSlug)->assertNotFound();
        $this->actingAs($user)->get('/diagrams/'.$currentSlug)->assertNotFound();
        $this->actingAs($user)->getJson('/diagrams/'.$renamedSlug.'/content')->assertNotFound();
    }

    public function test_deleting_a_diagram_that_has_revoked_and_active_shares_removes_them_too(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $revokedShare = Share::factory()->for($diagram)->for($user, 'author')->create(['revoked_at' => now()]);
        $activeShare = Share::factory()->for($diagram)->for($user, 'author')->create();

        $this->actingAs($user)->deleteJson('/diagrams/'.$diagram->slug)->assertOk();

        $this->assertDatabaseMissing('diagrams', ['id' => $diagram->id]);
        $this->assertDatabaseMissing('shares', ['id' => $revokedShare->id]);
        $this->assertDatabaseMissing('shares', ['id' => $activeShare->id]);
    }

    public function test_creating_a_diagram_leaves_no_record_when_the_disk_write_fails(): void
    {
        $user = User::factory()->create();

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::set('diagrams', $disk);

        $response = $this->actingAs($user)->post('/diagrams', ['title' => 'Doomed Diagram']);

        $response->assertServerError();
        $this->assertDatabaseMissing('diagrams', ['title' => 'Doomed Diagram']);
    }

    public function test_renaming_a_diagram_reverts_the_disk_move_when_the_database_transaction_fails(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Original Name']);
        $originalSlug = $diagram->slug;
        $originalPath = $diagram->disk_path;

        // Injects a failure inside the transaction (the "old slug"
        // bookkeeping row), the way a real constraint violation or deadlock
        // would, without reaching into the repository's internals.
        Event::listen('eloquent.creating: '.DiagramOldSlug::class, function (): void {
            throw new \RuntimeException('Simulated database failure mid-transaction.');
        });

        try {
            $response = $this->actingAs($user)->putJson('/diagrams/'.$originalSlug, [
                'title' => 'New Name',
            ]);
        } finally {
            Event::forget('eloquent.creating: '.DiagramOldSlug::class);
        }

        $response->assertServerError();

        $diagram->refresh();
        $this->assertSame($originalSlug, $diagram->slug);
        $this->assertSame('Original Name', $diagram->title);
        $this->assertSame($originalPath, $diagram->disk_path);
        $this->assertDatabaseMissing('diagram_old_slugs', ['diagram_id' => $diagram->id]);

        Storage::disk('diagrams')->assertExists($originalPath);

        $this->actingAs($user)->get('/diagrams/'.$originalSlug)->assertOk();
    }

    public function test_deleting_a_diagram_removes_the_record_even_when_removing_the_directory_fails(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'To Delete']);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('deleteDirectory')->once()->andThrow(new \RuntimeException('Simulated disk failure.'));
        Storage::set('diagrams', $disk);

        $response = $this->actingAs($user)->deleteJson('/diagrams/'.$diagram->slug);

        $response->assertServerError();
        $this->assertDatabaseMissing('diagrams', ['id' => $diagram->id]);
    }

    public function test_forking_a_diagram_registers_no_new_diagram_when_copying_the_content_fails(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Source Diagram']);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('get')->with($diagram->disk_path)->once()->andReturn(null);
        Storage::set('diagrams', $disk);

        $response = $this->actingAs($user)->post('/diagrams/'.$diagram->slug.'/fork');

        $response->assertServerError();
        $this->assertDatabaseCount('diagrams', 1);
        $this->assertDatabaseHas('diagrams', ['id' => $diagram->id]);
    }

    public function test_new_diagram_starts_with_its_creator_as_both_author_and_diagram_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/diagrams', ['title' => 'Fresh Diagram'])->assertRedirect();

        $diagram = Diagram::where('title', 'Fresh Diagram')->firstOrFail();
        $this->assertSame($user->id, $diagram->author_id);
        $this->assertSame($user->id, $diagram->owner_id);
    }

    public function test_forking_a_diagram_makes_the_forker_both_author_and_diagram_owner_of_the_fork(): void
    {
        $forker = User::factory()->create();
        $diagram = Diagram::factory()->for($forker, 'author')->create();

        $response = $this->actingAs($forker)->post('/diagrams/'.$diagram->slug.'/fork');
        $response->assertRedirect();

        $forked = Diagram::where('forked_from_id', $diagram->id)->firstOrFail();
        $this->assertSame($forker->id, $forked->author_id);
        $this->assertSame($forker->id, $forked->owner_id);
    }

    public function test_existing_diagrams_are_backfilled_with_the_author_as_diagram_owner(): void
    {
        $author = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $this->assertSame($author->id, $diagram->fresh()->owner_id);
    }

    public function test_only_the_diagram_owner_can_delete_a_diagram_when_it_differs_from_the_author(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $this->actingAs($author)->delete('/diagrams/'.$diagram->slug)->assertForbidden();
        $this->assertDatabaseHas('diagrams', ['id' => $diagram->id]);

        $this->actingAs($owner)->deleteJson('/diagrams/'.$diagram->slug)->assertOk();
        $this->assertDatabaseMissing('diagrams', ['id' => $diagram->id]);
    }

    public function test_the_diagram_owner_can_edit_even_when_not_the_author(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $this->actingAs($owner)->getJson('/diagrams/'.$diagram->slug.'/content')->assertOk();
    }

    public function test_the_author_alone_can_no_longer_open_the_editor_once_ownership_diverges(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $this->actingAs($author)->getJson('/diagrams/'.$diagram->slug.'/content')->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidContentProvider(): array
    {
        $ns = 'xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"';

        return [
            'not xml' => ['hello'],
            'malformed xml' => ["<bpmn:definitions {$ns}><bpmn:process></bpmn:definitions>"],
            'script break-out in element text' => ["<bpmn:definitions {$ns}></script><script>alert(1)</script></bpmn:definitions>"],
            'wrong root element' => ['<html><body>hi</body></html>'],
            'bpmn root without the BPMN namespace' => ['<definitions><process/></definitions>'],
            'doctype' => ["<!DOCTYPE bpmn:definitions><bpmn:definitions {$ns}/>"],
            'external entity (XXE)' => ["<!DOCTYPE d [<!ENTITY x SYSTEM \"file:///etc/passwd\">]><bpmn:definitions {$ns}>&x;</bpmn:definitions>"],
            'internal entity declaration' => ["<!DOCTYPE d [<!ENTITY a \"aaaa\">]><bpmn:definitions {$ns}>&a;</bpmn:definitions>"],
        ];
    }

    #[DataProvider('invalidContentProvider')]
    public function test_saving_content_that_is_not_safe_bpmn_xml_is_rejected(string $content): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $before = Storage::disk('diagrams')->get($diagram->disk_path);

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => $content,
        ])->assertUnprocessable()->assertJsonValidationErrors(['content' => 'The content is not valid BPMN 2.0 XML.']);

        $this->assertSame($before, Storage::disk('diagrams')->get($diagram->disk_path));
    }

    public function test_the_rejection_message_is_translated(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->withSession(['locale' => 'pt_BR'])
            ->putJson('/diagrams/'.$diagram->slug.'/content', ['content' => 'x'])
            ->assertJsonValidationErrors(['content' => 'O conteúdo não é um XML BPMN 2.0 válido.']);
    }

    public function test_saving_a_full_valid_bpmn_document_is_accepted(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => Diagram::emptyBpmn(),
        ])->assertOk();
    }
}

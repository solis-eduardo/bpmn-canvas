<?php

namespace Tests\Unit\Repositories;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Contract every VersionRepository adapter must pass (ADR-0018). Concrete
 * subclasses only say which adapter to build; the scenarios live here so the
 * git adapter and the database adapter cannot drift apart.
 *
 * The suffix is deliberately not "Test": this class is abstract and is run
 * through its concrete subclasses.
 */
abstract class VersionRepositoryContract extends TestCase
{
    use RefreshDatabase;

    /**
     * The adapter under test. It is also bound as the VersionRepository
     * interface for the whole test, so fixtures such as the Diagram factory
     * (which initializes a Version) go through the same adapter.
     */
    abstract protected function adapter(): VersionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        $this->app->instance(VersionRepository::class, $this->adapter());
    }

    /**
     * A persisted Diagram with content on disk and no Version history yet.
     */
    private function bareDiagram(string $content = '<bpmn>initial</bpmn>'): Diagram
    {
        $author = User::factory()->create(['name' => 'Ana Author']);
        $diagram = Diagram::factory()->for($author, 'author')->make();
        $diagram->save();

        Storage::disk('diagrams')->put($diagram->disk_path, $content);

        return $diagram;
    }

    private function author(Diagram $diagram): User
    {
        return $diagram->author;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function history(Diagram $diagram): array
    {
        return $this->adapter()->list($diagram);
    }

    public function test_initialize_records_the_content_on_disk_as_the_first_version(): void
    {
        $diagram = $this->bareDiagram('<bpmn>A</bpmn>');
        $repository = $this->adapter();

        $repository->initialize($diagram, $this->author($diagram));

        $history = $repository->list($diagram);
        $this->assertCount(1, $history);
        $this->assertSame(1, $history[0]['number']);
        $this->assertSame('Version 1', $history[0]['name']);
        $this->assertSame('Ana Author', $history[0]['author']);
        $this->assertSame('<bpmn>A</bpmn>', $repository->get($diagram, $history[0]['id']));
    }

    public function test_a_diagram_without_history_lists_nothing(): void
    {
        $diagram = $this->bareDiagram();

        $this->assertSame([], $this->adapter()->list($diagram));
    }

    public function test_save_with_content_writes_the_disk_and_records_a_new_version(): void
    {
        $diagram = $this->bareDiagram('<bpmn>A</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));

        $id = $repository->save($diagram, $this->author($diagram), '<bpmn>B</bpmn>');

        $history = $repository->list($diagram);
        $this->assertCount(2, $history);
        $this->assertSame($id, $history[0]['id']);
        $this->assertSame('<bpmn>B</bpmn>', $repository->get($diagram, $id));
        $this->assertSame('<bpmn>B</bpmn>', Storage::disk('diagrams')->get($diagram->disk_path));
        $this->assertSame('<bpmn>A</bpmn>', $repository->get($diagram, $history[1]['id']));
    }

    public function test_the_first_save_keeps_the_pre_existing_disk_content_as_the_initial_version(): void
    {
        $diagram = $this->bareDiagram('<bpmn>before</bpmn>');
        $repository = $this->adapter();

        $repository->save($diagram, $this->author($diagram), '<bpmn>after</bpmn>');

        $history = $repository->list($diagram);
        $this->assertCount(2, $history);
        $this->assertSame('<bpmn>before</bpmn>', $repository->get($diagram, $history[1]['id']));
        $this->assertSame('<bpmn>after</bpmn>', $repository->get($diagram, $history[0]['id']));
    }

    public function test_saving_identical_content_creates_no_version(): void
    {
        $diagram = $this->bareDiagram('<bpmn>A</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));

        $id = $repository->save($diagram, $this->author($diagram), '<bpmn>A</bpmn>');

        $this->assertNull($id);
        $this->assertCount(1, $repository->list($diagram));
    }

    public function test_save_with_allow_empty_records_a_version_even_without_changes(): void
    {
        $diagram = $this->bareDiagram('<bpmn>A</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));

        $id = $repository->save($diagram, $this->author($diagram), null, 'Marker', allowEmpty: true);

        $this->assertNotNull($id);
        $history = $repository->list($diagram);
        $this->assertCount(2, $history);
        $this->assertSame($id, $history[0]['id']);
        $this->assertSame('<bpmn>A</bpmn>', $repository->get($diagram, $id));
    }

    public function test_history_is_newest_first_and_numbered_from_the_oldest(): void
    {
        $diagram = $this->bareDiagram('<bpmn>1</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));
        $repository->save($diagram, $this->author($diagram), '<bpmn>2</bpmn>');
        $repository->save($diagram, $this->author($diagram), '<bpmn>3</bpmn>');

        $history = $repository->list($diagram);

        $this->assertSame([3, 2, 1], array_column($history, 'number'));
        $this->assertSame(['Version 3', 'Version 2', 'Version 1'], array_column($history, 'name'));
        $this->assertSame([false, false, false], array_column($history, 'renamed'));
        $this->assertSame([false, false, false], array_column($history, 'provisional'));
        $this->assertSame([false, false, false], array_column($history, 'hidden'));
        $this->assertContainsOnlyInstancesOf(CarbonInterface::class, array_column($history, 'saved_at'));
    }

    public function test_get_rejects_an_unknown_version(): void
    {
        $diagram = $this->bareDiagram();
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Version not found.');

        $repository->get($diagram, str_repeat('a', 40));
    }

    public function test_get_rejects_an_invalid_identifier(): void
    {
        $diagram = $this->bareDiagram();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid version identifier.');

        $this->adapter()->get($diagram, '--output=x');
    }

    public function test_exists_tells_a_real_version_from_anything_else(): void
    {
        $diagram = $this->bareDiagram();
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));
        $id = $repository->list($diagram)[0]['id'];

        $this->assertTrue($repository->exists($diagram, $id));
        $this->assertFalse($repository->exists($diagram, str_repeat('a', 40)));
        $this->assertFalse($repository->exists($diagram, 'not-a-version'));
        $this->assertFalse($repository->exists($diagram, '--output=x'));
    }

    public function test_restore_creates_a_new_version_on_top_and_keeps_the_history(): void
    {
        $diagram = $this->bareDiagram('<bpmn>A</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));
        $oldest = $repository->list($diagram)[0]['id'];
        $repository->save($diagram, $this->author($diagram), '<bpmn>B</bpmn>');
        $repository->save($diagram, $this->author($diagram), '<bpmn>C</bpmn>');

        $repository->restore($diagram, $oldest, $this->author($diagram));

        $history = $repository->list($diagram);
        $this->assertCount(4, $history);
        $this->assertSame(4, $history[0]['number']);
        $this->assertSame('<bpmn>A</bpmn>', Storage::disk('diagrams')->get($diagram->disk_path));
        $this->assertSame('<bpmn>A</bpmn>', $repository->get($diagram, $history[0]['id']));
        $this->assertSame('<bpmn>C</bpmn>', $repository->get($diagram, $history[1]['id']));
    }

    public function test_rename_names_a_version_and_flags_it_as_renamed(): void
    {
        $diagram = $this->bareDiagram();
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));
        $id = $repository->list($diagram)[0]['id'];

        $repository->rename($diagram, $id, 'Milestone');

        $version = $repository->list($diagram)[0];
        $this->assertSame('Milestone', $version['name']);
        $this->assertTrue($version['renamed']);
        $this->assertSame(1, $version['number']);
    }

    public function test_rename_rejects_an_unknown_version(): void
    {
        $diagram = $this->bareDiagram();
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));

        $this->expectException(RuntimeException::class);

        $repository->rename($diagram, str_repeat('b', 40), 'Ghost');
    }

    public function test_a_provisional_version_can_be_made_permanent_once(): void
    {
        $diagram = $this->bareDiagram('<bpmn>A</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));
        $repository->save($diagram, $this->author($diagram), '<bpmn>B</bpmn>');
        $id = $repository->list($diagram)[0]['id'];

        $repository->markProvisional($diagram, $id);
        $provisional = $repository->list($diagram)[0];
        $this->assertTrue($provisional['provisional']);
        $this->assertSame(__('messages.provisional_version'), $provisional['name']);

        $this->assertTrue($repository->makePermanent($diagram, $id));
        $this->assertFalse($repository->list($diagram)[0]['provisional']);
        $this->assertFalse($repository->makePermanent($diagram, $id));
    }

    public function test_a_hidden_version_keeps_its_number_and_still_resolves(): void
    {
        $diagram = $this->bareDiagram('<bpmn>A</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($diagram, $this->author($diagram));
        $repository->save($diagram, $this->author($diagram), '<bpmn>B</bpmn>');
        $repository->save($diagram, $this->author($diagram), '<bpmn>C</bpmn>');
        $hidden = $repository->list($diagram)[1]['id'];
        $repository->markProvisional($diagram, $hidden);

        $this->assertFalse($repository->hide($diagram, $repository->list($diagram)[0]['id']));
        $this->assertTrue($repository->hide($diagram, $hidden));

        $history = $repository->list($diagram);
        $this->assertSame([3, 2, 1], array_column($history, 'number'));
        $this->assertSame([false, true, false], array_column($history, 'hidden'));
        $this->assertSame('<bpmn>B</bpmn>', $repository->get($diagram, $hidden));
    }

    public function test_versions_are_isolated_per_diagram(): void
    {
        $first = $this->bareDiagram('<bpmn>first</bpmn>');
        $second = $this->bareDiagram('<bpmn>second</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($first, $this->author($first));
        $repository->initialize($second, $this->author($second));

        $firstId = $repository->list($first)[0]['id'];
        $secondId = $repository->list($second)[0]['id'];

        $this->assertNotSame($firstId, $secondId);
        $this->assertCount(1, $repository->list($first));
        $this->assertFalse($repository->exists($second, $firstId));

        $this->expectException(RuntimeException::class);

        $repository->get($second, $firstId);
    }

    /**
     * A fork starts its own history from the content it was copied with
     * (ForkService calls initialize), and later Versions of either Diagram
     * stay separate.
     */
    public function test_a_fork_starts_its_own_history_from_the_source_content(): void
    {
        $source = $this->bareDiagram('<bpmn>source</bpmn>');
        $repository = $this->adapter();
        $repository->initialize($source, $this->author($source));
        $repository->save($source, $this->author($source), '<bpmn>source, edited</bpmn>');

        $fork = $this->bareDiagram('<bpmn>source, edited</bpmn>');
        $repository->initialize($fork, $this->author($fork));

        $forkHistory = $repository->list($fork);
        $this->assertCount(1, $forkHistory);
        $this->assertSame('<bpmn>source, edited</bpmn>', $repository->get($fork, $forkHistory[0]['id']));

        $repository->save($source, $this->author($source), '<bpmn>source, edited again</bpmn>');

        $this->assertCount(1, $repository->list($fork));
        $this->assertCount(3, $repository->list($source));
    }
}

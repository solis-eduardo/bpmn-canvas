<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\BuildsAccessScenarios;

/**
 * Repeatable query-count measurement for issue #241. Run with
 * `MEASURE_QUERIES=1 php artisan test --filter=DiagramAccessQueryCountTest`
 * to print the numbers; the assertions pin the ceiling reached with the
 * request-scoped access memo.
 */
class DiagramAccessQueryCountTest extends TestCase
{
    use BuildsAccessScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_opening_the_editor_evaluates_each_access_predicate_once(): void
    {
        [, $diagram, $member] = $this->workspaceDiagramWithMember();

        $access = $this->measure('GET /diagrams/{slug}', function () use ($diagram, $member) {
            $this->actingAs($member)->get('/diagrams/'.$diagram->slug)->assertOk();
        });

        $this->assertSame(array_unique($access), $access, 'no access predicate runs twice');
    }

    public function test_a_heartbeat_evaluates_each_access_predicate_once(): void
    {
        [, $diagram, $member] = $this->workspaceDiagramWithMember();
        $this->actingAs($member)->get('/diagrams/'.$diagram->slug)->assertOk();

        $access = $this->measure('PATCH /diagrams/{slug}/lock', function () use ($diagram, $member) {
            $this->actingAs($member)->patch('/diagrams/'.$diagram->slug.'/lock')->assertSuccessful();
        });

        $this->assertCount(1, $access);
        $this->assertSame(array_unique($access), $access, 'no access predicate runs twice');
    }

    /**
     * Runs $callback and returns the access-predicate queries it ran (the
     * "select exists" ones behind isEditableBy, hasApprovedCollaborator and
     * hasManagerRole). With MEASURE_QUERIES set, also prints their count and
     * the total query count.
     *
     * @param  callable(): void  $callback
     * @return list<string>
     */
    private function measure(string $label, callable $callback): array
    {
        $queries = $this->queriesRunBy($callback);
        $access = array_values(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'select exists')));

        if (getenv('MEASURE_QUERIES')) {
            fwrite(STDERR, sprintf("\n[queries] %s: total=%d access=%d\n", $label, count($queries), count($access)));
        }

        return $access;
    }
}

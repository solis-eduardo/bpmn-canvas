<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Repeatable query-count measurement for issue #241. Run with
 * `MEASURE_QUERIES=1 php artisan test --filter=DiagramAccessQueryCountTest`
 * to print the numbers; the assertions pin the ceiling reached with the
 * request-scoped access memo.
 */
class DiagramAccessQueryCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_opening_the_editor_evaluates_each_access_predicate_once(): void
    {
        [$diagram, $member] = $this->workspaceDiagramWithMember();

        $access = $this->measure('GET /diagrams/{slug}', function () use ($diagram, $member) {
            $this->actingAs($member)->get('/diagrams/'.$diagram->slug)->assertOk();
        });

        $this->assertSame(array_unique($access), $access, 'no access predicate runs twice');
    }

    public function test_a_heartbeat_evaluates_each_access_predicate_once(): void
    {
        [$diagram, $member] = $this->workspaceDiagramWithMember();
        $this->actingAs($member)->get('/diagrams/'.$diagram->slug)->assertOk();

        $access = $this->measure('PATCH /diagrams/{slug}/lock', function () use ($diagram, $member) {
            $this->actingAs($member)->patch('/diagrams/'.$diagram->slug.'/lock')->assertSuccessful();
        });

        $this->assertCount(1, $access);
        $this->assertSame(array_unique($access), $access, 'no access predicate runs twice');
    }

    /**
     * @return array{Diagram, User}
     */
    private function workspaceDiagramWithMember(): array
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create(['workspace_id' => $workspace->id]);
        $member = User::factory()->create();
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'origin' => Member::ORIGIN_MANUAL]);

        return [$diagram, $member];
    }

    /**
     * Runs $callback and returns the access-predicate queries it ran (the
     * "select exists" ones behind isEditableBy, hasApprovedCollaborator and
     * hasManagerRole), as SQL plus bindings. With MEASURE_QUERIES set, also
     * prints their count and the total query count.
     *
     * @param  callable(): void  $callback
     * @return list<string>
     */
    private function measure(string $label, callable $callback): array
    {
        $total = 0;
        $access = [];

        DB::listen(function ($query) use (&$total, &$access) {
            $total++;

            if (str_starts_with($query->sql, 'select exists')) {
                $access[] = $query->sql.json_encode($query->bindings);
            }
        });

        $callback();

        if (getenv('MEASURE_QUERIES')) {
            fwrite(STDERR, sprintf("\n[queries] %s: total=%d access=%d\n", $label, $total, count($access)));
        }

        return $access;
    }
}

<?php

namespace Tests\Traits;

use App\Models\Diagram;
use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Shared set-up and query counting for the access-memo tests (issue #241).
 */
trait BuildsAccessScenarios
{
    /**
     * @return array{Workspace, Diagram, User}
     */
    private function workspaceDiagramWithMember(): array
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create(['workspace_id' => $workspace->id]);
        $member = User::factory()->create();
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'origin' => Member::ORIGIN_MANUAL]);

        return [$workspace, $diagram, $member];
    }

    /**
     * Runs $callback and returns the SQL (with bindings) of every query it ran.
     *
     * @param  callable(): void  $callback
     * @return list<string>
     */
    private function queriesRunBy(callable $callback): array
    {
        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql.json_encode($query->bindings);
        });

        $callback();

        return $queries;
    }
}

<?php

namespace Tests\Unit\Models;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiagramIsEditableByTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_checks_on_one_instance_query_the_database_once_per_user(): void
    {
        $diagram = Diagram::factory()->create();
        $stranger = User::factory()->create();

        $queries = $this->countDiagramQueries(function () use ($diagram, $stranger) {
            $this->assertTrue($diagram->isEditableBy($diagram->owner));
            $this->assertTrue($diagram->isEditableBy($diagram->owner));
            $this->assertFalse($diagram->isEditableBy($stranger));
            $this->assertFalse($diagram->isEditableBy($stranger));
        });

        $this->assertSame(2, $queries);
    }

    public function test_refresh_drops_the_memoized_answers(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create(['workspace_id' => $workspace->id]);
        $member = User::factory()->create();
        $this->addMember($workspace, $member);

        $this->assertTrue($diagram->isEditableBy($member));

        $workspace->members()->where('user_id', $member->id)->delete();

        $this->assertTrue($diagram->isEditableBy($member), 'the answer is memoized until the instance is re-read');
        $this->assertFalse($diagram->refresh()->isEditableBy($member));
    }

    public function test_saving_the_instance_drops_the_memoized_answers(): void
    {
        $diagram = Diagram::factory()->create();
        $newOwner = User::factory()->create();

        $this->assertFalse($diagram->isEditableBy($newOwner));

        $diagram->update(['owner_id' => $newOwner->id]);

        $this->assertTrue($diagram->isEditableBy($newOwner));
    }

    /**
     * @param  callable(): void  $callback
     */
    private function countDiagramQueries(callable $callback): int
    {
        $count = 0;

        DB::listen(function ($query) use (&$count) {
            if (str_contains($query->sql, 'diagrams')) {
                $count++;
            }
        });

        $callback();

        return $count;
    }
}

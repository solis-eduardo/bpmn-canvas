<?php

namespace Tests\Unit\Repositories;

use App\Models\Tag;
use App\Repositories\TagRepository;
use Exception;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_tag_on_first_use_and_reuses_it_after(): void
    {
        $repository = new TagRepository;

        $first = $repository->findOrCreateByName('finance');
        $second = $repository->findOrCreateByName('finance');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Tag::count());
    }

    public function test_resolves_to_the_row_created_by_a_concurrent_request(): void
    {
        // Simulates losing the select-then-insert race: the other request's
        // row lands right after our select came back empty, so our insert
        // hits the unique index.
        $raced = false;
        DB::listen(function (QueryExecuted $query) use (&$raced) {
            if ($raced || ! str_starts_with($query->sql, 'select')) {
                return;
            }

            $raced = true;
            DB::table('tags')->insert(['name' => 'finance', 'created_at' => now()]);
        });

        $tag = (new TagRepository)->findOrCreateByName('finance');

        $this->assertSame('finance', $tag->name);
        $this->assertSame(1, Tag::count());
    }

    public function test_rethrows_when_the_failure_was_not_a_race(): void
    {
        Tag::creating(function () {
            throw new QueryException('sqlite', 'insert into tags', [], new Exception('disk I/O error'));
        });

        $this->expectException(QueryException::class);

        (new TagRepository)->findOrCreateByName('finance');
    }
}

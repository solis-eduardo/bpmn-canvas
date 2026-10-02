<?php

namespace Tests\Unit\Repositories;

use App\Repositories\ListSort;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ListSortTest extends TestCase
{
    private const COLUMNS = ['title' => 'asc', 'updated_at' => 'desc'];

    private function sortFor(array $query): ListSort
    {
        return ListSort::fromRequest(Request::create('/diagrams', 'GET', $query), self::COLUMNS);
    }

    public function test_it_defaults_to_the_first_column_with_its_direction(): void
    {
        $sort = $this->sortFor([]);

        $this->assertSame(['title', 'asc'], [$sort->column, $sort->direction]);
    }

    public function test_a_column_takes_its_own_default_direction(): void
    {
        $this->assertSame('desc', $this->sortFor(['sort' => 'updated_at'])->direction);
    }

    public function test_an_explicit_direction_wins_and_unknown_values_fall_back(): void
    {
        $this->assertSame('asc', $this->sortFor(['sort' => 'updated_at', 'direction' => 'asc'])->direction);

        $sort = $this->sortFor(['sort' => 'password; drop', 'direction' => 'sideways']);
        $this->assertSame(['title', 'asc'], [$sort->column, $sort->direction]);

        $sort = $this->sortFor(['sort' => ['title']]);
        $this->assertSame('title', $sort->column);
    }

    public function test_clicking_the_sorted_header_flips_the_direction_and_another_header_uses_its_default(): void
    {
        $sort = $this->sortFor(['sort' => 'title', 'direction' => 'asc']);

        $this->assertSame('desc', $sort->nextDirection('title'));
        $this->assertSame('desc', $sort->nextDirection('updated_at'));
        $this->assertSame('asc', $this->sortFor(['sort' => 'title', 'direction' => 'desc'])->nextDirection('title'));
    }
}

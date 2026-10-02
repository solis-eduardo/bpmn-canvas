<?php

namespace Tests\Unit\Repositories;

use App\Repositories\DiagramListSort;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class DiagramListSortTest extends TestCase
{
    private function sortFor(array $query): DiagramListSort
    {
        return DiagramListSort::fromRequest(Request::create('/diagrams', 'GET', $query));
    }

    public function test_it_defaults_to_the_title_ascending(): void
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
    }
}

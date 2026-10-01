<?php

namespace Tests\Unit\Repositories;

use App\Models\Tag;
use App\Repositories\TagRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

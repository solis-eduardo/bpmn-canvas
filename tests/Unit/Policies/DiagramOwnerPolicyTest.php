<?php

namespace Tests\Unit\Policies;

use App\Models\Diagram;
use App\Models\User;
use App\Policies\DiagramOwnerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramOwnerPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_is_for_the_diagram_owner_and_administrators(): void
    {
        $diagram = Diagram::factory()->create();
        $policy = new DiagramOwnerPolicy;

        $this->assertTrue($policy->transfer($diagram->owner, $diagram));
        $this->assertTrue($policy->transfer(User::factory()->create(['is_admin' => true]), $diagram));
        $this->assertFalse($policy->transfer(User::factory()->create(), $diagram));
    }

    public function test_the_author_loses_the_power_once_ownership_moves(): void
    {
        $diagram = Diagram::factory()->create();
        $author = $diagram->author;
        $diagram->update(['owner_id' => User::factory()->create()->id]);

        $this->assertFalse((new DiagramOwnerPolicy)->transfer($author, $diagram->fresh()));
    }
}

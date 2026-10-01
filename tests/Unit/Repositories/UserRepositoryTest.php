<?php

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_by_id_email_and_keycloak_id(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'kc-1']);
        $repository = new UserRepository;

        $this->assertTrue($repository->find($user->id)->is($user));
        $this->assertTrue($repository->findOrFail($user->id)->is($user));
        $this->assertTrue($repository->findByEmail($user->email)->is($user));
        $this->assertTrue($repository->findByKeycloakId('kc-1')->is($user));
        $this->assertNull($repository->find(0));
        $this->assertNull($repository->findByEmail('nobody@example.com'));
        $this->assertNull($repository->findByKeycloakId('missing'));
    }

    public function test_find_or_fail_throws_for_a_missing_user(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new UserRepository)->findOrFail(0);
    }

    public function test_lists_users_ordered_by_name(): void
    {
        User::factory()->create(['name' => 'Bruno']);
        User::factory()->create(['name' => 'Ana']);

        $names = (new UserRepository)->allOrderedByName()->pluck('name')->all();

        $this->assertSame(['Ana', 'Bruno'], $names);
    }

    public function test_creates_a_user(): void
    {
        $user = (new UserRepository)->create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-123']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'ana@example.com']);
    }

    public function test_invalidates_only_the_other_sessions_of_the_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        foreach ([['keep', $user->id], ['drop', $user->id], ['foreign', $other->id]] as [$id, $userId]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
        }

        $ended = (new UserRepository)->invalidateOtherSessions($user, 'keep');

        $this->assertSame(1, $ended);
        $this->assertSame(['foreign', 'keep'], DB::table('sessions')->orderBy('id')->pluck('id')->all());
    }
}

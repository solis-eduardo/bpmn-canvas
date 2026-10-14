<?php

namespace App\Providers;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use App\Support\AccessMemo;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the request-scoped AccessMemo (issue #241) and the invalidation that
 * keeps it correct.
 */
class AccessMemoServiceProvider extends ServiceProvider
{
    /**
     * The models whose rows the memoized access predicates read.
     */
    private const ACCESS_MODELS = [Diagram::class, Collaborator::class, Member::class, Workspace::class, User::class];

    public function register(): void
    {
        // Per request (and per queued job): see AccessMemo.
        $this->app->scoped(AccessMemo::class);
    }

    /**
     * Any save or delete of an access row clears the memo, whichever model
     * instance made it. Writes through the query builder fire no model
     * event; their repositories clear the memo themselves.
     */
    public function boot(): void
    {
        foreach (self::ACCESS_MODELS as $model) {
            foreach (['saved', 'deleted'] as $event) {
                Event::listen("eloquent.{$event}: {$model}", fn () => $this->app->make(AccessMemo::class)->forget());
            }
        }

        Event::listen(RequestHandled::class, fn () => $this->app->make(AccessMemo::class)->forget());
    }
}

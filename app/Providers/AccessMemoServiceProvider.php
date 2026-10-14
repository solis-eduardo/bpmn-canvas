<?php

namespace App\Providers;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use App\Support\AccessMemo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the request-scoped AccessMemo (issue #241) and the invalidation that
 * keeps it correct. See AccessMemo for the rules.
 */
class AccessMemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Per request (and per queued job): see AccessMemo.
        $this->app->scoped(AccessMemo::class);
    }

    public function boot(): void
    {
        $forget = fn () => app(AccessMemo::class)->forget();

        // The rows that grant access: any change matters.
        foreach ([Collaborator::class, Member::class] as $model) {
            Event::listen("eloquent.saved: {$model}", $forget);
            Event::listen("eloquent.deleted: {$model}", $forget);
        }

        // The Diagram's owner and Workspace decide access; its title, Lock
        // columns and the like do not.
        Event::listen('eloquent.saved: '.Diagram::class, function (Model $diagram) use ($forget) {
            if ($diagram->wasChanged(['owner_id', 'workspace_id'])) {
                $forget();
            }
        });

        // A new Diagram can have answers memoized for its (reused) id.
        Event::listen('eloquent.created: '.Diagram::class, $forget);

        // Deleting these cascades access rows away in the database.
        foreach ([Diagram::class, Workspace::class, User::class] as $model) {
            Event::listen("eloquent.deleted: {$model}", $forget);
        }

        Event::listen(TransactionRolledBack::class, $forget);
        Event::listen(RequestHandled::class, $forget);
    }
}

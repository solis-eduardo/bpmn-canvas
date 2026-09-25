<?php

namespace App\Providers;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\DiagramPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Authorization wiring (Gates and Policies), split out of
 * AppServiceProvider to keep its coupling under the PHPMD threshold
 * (phpmd.xml, CouplingBetweenObjects) as the number of Policies grows.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->is_admin);

        Gate::policy(Diagram::class, DiagramPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);
    }
}

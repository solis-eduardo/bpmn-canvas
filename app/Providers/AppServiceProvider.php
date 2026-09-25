<?php

namespace App\Providers;

use App\Listeners\CheckDatabaseHealth;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\DiagramPolicy;
use App\Policies\WorkspacePolicy;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\DatabaseRestorer;
use App\Services\Backup\PgDumpDatabaseDumper;
use App\Services\Backup\PgRestoreDatabaseRestorer;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseDumper::class, PgDumpDatabaseDumper::class);
        $this->app->bind(DatabaseRestorer::class, PgRestoreDatabaseRestorer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->is_admin);

        Gate::policy(Diagram::class, DiagramPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);

        Event::listen(SocialiteWasCalled::class, [KeycloakExtendSocialite::class, 'handle']);
        Event::listen(DiagnosingHealth::class, CheckDatabaseHealth::class);
    }
}

<?php

namespace App\Providers;

use App\Models\Diagram;
use App\Models\User;
use App\Policies\DiagramPolicy;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\PgDumpDatabaseDumper;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->is_admin);

        Gate::policy(Diagram::class, DiagramPolicy::class);

        Event::listen(SocialiteWasCalled::class, [KeycloakExtendSocialite::class, 'handle']);
    }
}

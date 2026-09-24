<?php

namespace App\Providers;

use App\Models\Diagrama;
use App\Models\User;
use App\Policies\DiagramaPolicy;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->is_admin);

        Gate::policy(Diagrama::class, DiagramaPolicy::class);

        Event::listen(SocialiteWasCalled::class, [KeycloakExtendSocialite::class, 'handle']);
    }
}

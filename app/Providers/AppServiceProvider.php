<?php

namespace App\Providers;

use App\Listeners\CheckDatabaseHealth;
use App\Repositories\DatabaseVersionRepository;
use App\Repositories\GitVersionRepository;
use App\Repositories\VersionRepository;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\DatabaseRestorer;
use App\Services\Backup\PgDumpDatabaseDumper;
use App\Services\Backup\PgRestoreDatabaseRestorer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
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

        // The Version store is chosen by configuration (ADR-0018). Resolved per
        // call so the choice always follows the current config.
        $this->app->bind(VersionRepository::class, function (Application $app): VersionRepository {
            $store = config('bpmn.version_store');

            return match ($store) {
                'git' => $app->make(GitVersionRepository::class),
                'database' => $app->make(DatabaseVersionRepository::class),
                default => throw new InvalidArgumentException("Unsupported VERSION_STORE value [{$store}]: use git or database."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, [KeycloakExtendSocialite::class, 'handle']);
        Event::listen(DiagnosingHealth::class, CheckDatabaseHealth::class);
    }
}

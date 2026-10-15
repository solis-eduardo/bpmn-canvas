<?php

namespace App\Providers;

use App\Auth\DeactivatedAwareUserProvider;
use App\Listeners\CheckDatabaseHealth;
use App\Models\User;
use App\Repositories\DatabaseVersionRepository;
use App\Repositories\GitVersionRepository;
use App\Repositories\VersionRepository;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\DatabaseRestorer;
use App\Services\Backup\PgDumpDatabaseDumper;
use App\Services\Backup\PgRestoreDatabaseRestorer;
use App\Support\KeycloakProvider;
use App\Support\PublicAssets;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped so each request resolves its own base URL and hashes each file once.
        $this->app->scoped(PublicAssets::class, fn () => new PublicAssets(public_path('assets'), asset('assets')));

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
        // Resolves the signed-in User even when deactivated, so EnsureUserIsActive can sign them out (ADR-0020).
        Auth::provider('eloquent-with-deactivated', fn (Application $app, array $config) => new DeactivatedAwareUserProvider($app['hash'], $config['model']));

        // The admin screens address a User by id whatever its state (ADR-0020).
        Route::bind('managedUser', fn (string $id) => User::withDeactivated()->findOrFail($id));

        // A deactivated User receives nothing: no database row, no email.
        Event::listen(NotificationSending::class, fn (NotificationSending $event) => ! ($event->notifiable instanceof User && $event->notifiable->isDeactivated()));

        // `@assetUrl('editor.js')`: content-versioned URL of a file in public/assets/.
        Blade::directive('assetUrl', fn (string $expression) => "<?php echo e(app(\\App\\Support\\PublicAssets::class)->url({$expression})); ?>");

        Event::listen(SocialiteWasCalled::class, fn (SocialiteWasCalled $event) => $event->extendSocialite('keycloak', KeycloakProvider::class));
        Event::listen(DiagnosingHealth::class, CheckDatabaseHealth::class);
    }
}

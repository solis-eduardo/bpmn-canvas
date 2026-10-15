<?php

use App\Http\Middleware\AddContentSecurityPolicy;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\KeepJsonOutOfPreviousUrl;
use App\Http\Middleware\SetLocale;
use App\Support\LocalizedJsonError;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app always sits behind the external reverse proxy that
        // terminates TLS (see docker-compose.yml's `nginx-network`), so the
        // proxy is trusted to report the original scheme/host via the
        // `X-Forwarded-*` headers. Without this, generated URLs (including
        // signed Share links) come out as `http://`, which browsers reject
        // for a link that was actually served over `https://`.
        //
        // Deliberately excludes HEADER_X_FORWARDED_FOR: trusting that header
        // would let a client spoof its own IP (e.g. to dodge the login
        // route's rate limiter, which keys off Request::ip()), and nothing
        // here needs the forwarded client IP — only the forwarded
        // scheme/host/port used for URL generation.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Before StartSession, which records the previous URL.
        $middleware->web(prepend: [
            KeepJsonOutOfPreviousUrl::class,
        ], append: [
            AddContentSecurityPolicy::class,
            SetLocale::class,
            EnsureUserIsActive::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Runs from the dedicated `scheduler` compose service (no host
        // cron, see docker-compose.yml). A quiet, off-peak hour, staggered
        // away from :00 to dodge the crowd of jobs everyone else also
        // schedules on the hour.
        $schedule->command('backup:run')->dailyAt('02:15')->onOneServer()->when(fn () => config('bpmn.backups_enabled'));
        // Restores the artifact backup:run just made into a throwaway
        // database and directory (ADR-0026); runs well after it so the
        // artifact (and its off-site upload) is complete.
        $schedule->command('backup:verify')->dailyAt('03:15')->onOneServer()->when(fn () => config('bpmn.backups_enabled'));
        // Offset from :00, same reasoning as backup:run above — avoids the
        // crowd of jobs everyone else schedules on the hour.
        $schedule->command('shares:warn-expiring')->hourlyAt(45)->onOneServer();
        $schedule->command('activity:prune')->dailyAt('03:20')->onOneServer();
        // Lock expiry (ADR-0003) is time-sensitive, so it runs every minute.
        // Its output is kept: a failure here leaves a Lock stuck, and without
        // a trace nobody finds out why.
        $schedule->command('locks:expire')
            ->everyMinute()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/locks-expire.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON clients (the Editor and its panels) show `message` to the
        // User, so Laravel's English defaults are swapped for localized
        // ones. {@see LocalizedJsonError} decides which; deliberate
        // messages of ours and validation errors are left alone.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            if (! $response instanceof JsonResponse || ! $request->expectsJson()) {
                return $response;
            }

            $message = LocalizedJsonError::messageFor($e, $response);

            if ($message === null) {
                return $response;
            }

            return $response->setData(array_merge($response->getData(true), ['message' => $message]));
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

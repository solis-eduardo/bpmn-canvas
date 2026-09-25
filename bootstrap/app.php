<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Runs from the dedicated `scheduler` compose service (no host
        // cron, see docker-compose.yml). A quiet, off-peak hour, staggered
        // away from :00 to dodge the crowd of jobs everyone else also
        // schedules on the hour.
        $schedule->command('backup:run')->dailyAt('02:15')->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

<?php

use App\Http\Middleware\SetLocale;
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

        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

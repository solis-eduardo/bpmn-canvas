<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the demo routes out of reach unless DEMO_MODE is on.
 */
class EnsureDemoMode
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Demo::enabled(), 404);

        return $next($request);
    }
}

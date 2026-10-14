<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel remembers the last GET as the "previous URL" unless the request is
 * AJAX, and it only recognizes AJAX by `X-Requested-With`. Our scripts poll JSON
 * endpoints (the notification bell) with `Accept: application/json` alone, so a
 * poll landing between a page load and a redirect()->back() (the language switch)
 * made the User land on the raw JSON. Marking JSON-expecting requests as AJAX
 * keeps them out of the previous URL.
 */
class KeepJsonOutOfPreviousUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->expectsJson()) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        return $next($request);
    }
}

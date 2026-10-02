<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth against XSS: even if some user-controlled content slips
 * past output escaping, the browser refuses to run script that isn't from
 * this origin or tagged with this request's nonce.
 *
 * Everything is served from the app's own origin (bpmn-js is vendored, no
 * CDN, no websockets — realtime features poll over fetch), so the policy is
 * `'self'` plus a per-request nonce for the few inline scripts (the
 * Editor's import map). Blade views read the nonce from `$cspNonce`.
 */
class AddContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));

        View::share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));

        return $response;
    }

    /**
     * `npm run dev` serves assets and HMR from the Vite dev server; empty
     * otherwise. Prefixed with a space, ready to append to a directive.
     */
    private function viteOrigin(): string
    {
        return Vite::isRunningHot()
            ? ' '.rtrim(file_get_contents(Vite::hotFile()) ?: '', "/\n")
            : '';
    }

    private function policy(string $nonce): string
    {
        $vite = $this->viteOrigin();

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$vite}",
            // bpmn-js positions every shape through inline `style` attributes,
            // which only 'unsafe-inline' allows; styles can't run script.
            "style-src 'self' 'unsafe-inline'{$vite}",
            // data:/blob: — bpmn-js marker/icon images and PNG export.
            "img-src 'self' data: blob:",
            // data: — bpmn-font embedded in bpmn-embedded.css.
            "font-src 'self' data:{$vite}",
            "connect-src 'self'".($vite !== '' ? $vite.' '.str_replace(['http://', 'https://'], ['ws://', 'wss://'], trim($vite)) : ''),
            "object-src 'none'",
            "base-uri 'self'",
            // The Keycloak login is a plain link (GET navigation), not a form
            // post, so it isn't restricted by form-action.
            "form-action 'self'",
            // Nothing embeds the app in a frame.
            "frame-ancestors 'none'",
        ];

        return implode('; ', $directives);
    }
}

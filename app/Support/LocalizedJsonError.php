<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * The `message` a JSON error response carries, in the User's language.
 *
 * The Editor and its panels show that field to the User directly, so
 * Laravel's own messages — hardcoded English, written for developers —
 * would reach them untranslated. This swaps those for localized ones.
 *
 * It keys off the exception *type*, never the message text: what has to be
 * decided is "did the framework raise this, or did we?", and an
 * `abort(403, '...')` of ours is an HttpException carrying its own
 * message. Matching Laravel's English prose would answer that question by
 * accident, and silently stop answering it on an upgrade that rewords a
 * default.
 */
class LocalizedJsonError
{
    /**
     * The replacement message, or null to leave the response alone —
     * because the status needs no rewriting, or because the message is
     * deliberately ours.
     */
    public static function messageFor(Throwable $e, JsonResponse $response): ?string
    {
        // Validation errors are already localized, field by field.
        if ($e instanceof ValidationException) {
            return null;
        }

        // Laravel wraps several of these in an HttpException before the
        // response is built, keeping the original as `previous` — so the
        // type that says who raised it is found on either.
        $cause = $e->getPrevious() ?? $e;

        $key = match (true) {
            $e instanceof AuthorizationException,
            $cause instanceof AuthorizationException,
            $e instanceof AuthenticationException,
            $cause instanceof AuthenticationException => 'error_forbidden',
            $e instanceof ModelNotFoundException,
            $cause instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => 'error_not_found',
            $e instanceof TokenMismatchException,
            $cause instanceof TokenMismatchException => 'error_session_expired',
            $e instanceof TooManyRequestsHttpException => 'error_too_many_requests',
            default => self::keyForStatus($e, $response->getStatusCode()),
        };

        return $key === null ? null : __('messages.'.$key);
    }

    /**
     * For an exception whose class says nothing, the status still can. A
     * deliberate abort() reaches here as an HttpException with a message of
     * its own, which is kept: only a bare one is replaced.
     */
    private static function keyForStatus(Throwable $e, int $status): ?string
    {
        // A server error's own message describes the failure to a
        // developer, so it is replaced rather than kept — unless debugging,
        // where seeing it is the point.
        if ($status >= 500) {
            return config('app.debug') ? null : 'error_server';
        }

        if ($e instanceof HttpExceptionInterface && $e->getMessage() !== '') {
            return null;
        }

        return match ($status) {
            403 => 'error_forbidden',
            404 => 'error_not_found',
            419 => 'error_session_expired',
            429 => 'error_too_many_requests',
            default => null,
        };
    }
}

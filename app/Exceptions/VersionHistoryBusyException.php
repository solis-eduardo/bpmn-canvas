<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Another writer kept a Diagram's Version history locked for the whole wait
 * (SharedVersionRepository::save(), issue #242). Nothing was written, so the
 * same request can simply be retried. Answers 503 with Retry-After, not the
 * 423 of an unavailable Lock: the Editor reads a 423 as "the Lock is gone".
 *
 * Deliberately not a RuntimeException: the controllers turn that into a 404
 * for an unknown Version.
 */
class VersionHistoryBusyException extends Exception
{
    /** Seconds the client is told to wait before retrying. */
    public const RETRY_AFTER_SECONDS = 5;

    public static function fromTimeout(LockTimeoutException $timeout): self
    {
        return new self('The Version history is busy.', previous: $timeout);
    }

    /**
     * An expected, transient condition: no stack trace in the log. The caller
     * that swallows it (locks:expire) logs it itself.
     */
    public function report(): bool
    {
        return true;
    }

    public function render(Request $request): SymfonyResponse
    {
        $message = __('messages.error_history_busy');
        $headers = ['Retry-After' => (string) self::RETRY_AFTER_SECONDS];

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], 503, $headers);
        }

        return new Response($message, 503, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

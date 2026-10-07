<?php

namespace App\Exceptions;

use App\Models\Diagram;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A write needed the Diagram's Lock and could not have it (CONTEXT.md,
 * "Lock"). Always a 423, with a message of its own that the JSON error
 * localization leaves alone, so every path that needs the Lock answers the
 * same way.
 */
class LockUnavailableException extends HttpException
{
    /** Another User holds the Lock. */
    public static function heldByOther(Diagram $diagram): self
    {
        return new self(423, __('messages.error_diagram_locked_by', [
            'name' => $diagram->lockedBy->name,
        ]));
    }

    /** The Lock was free a moment ago, but someone else took it first. */
    public static function lost(): self
    {
        return new self(423, __('messages.error_lock_lost'));
    }
}

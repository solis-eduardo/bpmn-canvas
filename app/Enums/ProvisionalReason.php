<?php

namespace App\Enums;

/**
 * Why a Lock's unsaved content was kept as a provisional Version (ADR-0003).
 * Each reason has its own accurate name: a Lock released by hand did not
 * expire.
 */
enum ProvisionalReason: string
{
    case Expired = 'expired';
    case Released = 'released';

    /** Commit message of the Version in the storage engine. */
    public function message(): string
    {
        return match ($this) {
            self::Expired => 'Provisional version (Lock expired)',
            self::Released => 'Provisional version (Lock released)',
        };
    }

    /** The localized name shown for a provisional Version that was not renamed. */
    public function label(): string
    {
        return match ($this) {
            self::Expired => __('messages.provisional_version'),
            self::Released => __('messages.provisional_version_released'),
        };
    }
}

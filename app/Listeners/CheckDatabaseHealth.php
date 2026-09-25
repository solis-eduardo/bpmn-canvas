<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Confirms the database connection is reachable as part of the app's
 * `/up` health check, so the check only passes when the app can actually
 * serve requests that touch the database.
 */
class CheckDatabaseHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            throw new RuntimeException('Database connection is unreachable.', previous: $e);
        }
    }
}

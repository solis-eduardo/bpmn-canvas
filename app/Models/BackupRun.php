<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One outcome of a backup-related job (ADR-0026): `backup:run` producing an
 * artifact, its off-site upload, or `backup:verify` restoring it. Kept in the
 * database so Administrators can see when each last succeeded.
 *
 * @property string $kind
 * @property bool $succeeded
 * @property ?string $artifact
 * @property ?string $message
 * @property Carbon $created_at
 */
class BackupRun extends Model
{
    public const KIND_BACKUP = 'backup';

    public const KIND_OFFSITE = 'offsite';

    public const KIND_VERIFICATION = 'verification';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['succeeded' => 'boolean'];
    }

    public static function record(string $kind, bool $succeeded, ?string $artifact = null, ?string $message = null): self
    {
        return self::create([
            'kind' => $kind,
            'succeeded' => $succeeded,
            'artifact' => $artifact,
            'message' => $message,
        ]);
    }

    public static function lastSuccess(string $kind): ?self
    {
        return self::latestOutcome($kind, true);
    }

    /**
     * The most recent failure, only while no success has followed it.
     */
    public static function latestFailure(string $kind): ?self
    {
        $failure = self::latestOutcome($kind, false);
        $success = self::lastSuccess($kind);

        return $failure && (! $success || $failure->id > $success->id) ? $failure : null;
    }

    private static function latestOutcome(string $kind, bool $succeeded): ?self
    {
        return self::where('kind', $kind)->where('succeeded', $succeeded)->latest('id')->first();
    }
}

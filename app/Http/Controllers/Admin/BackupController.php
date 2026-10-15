<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use Illuminate\View\View;

/**
 * Read-only status page for Administrators: when the backup, its off-site
 * copy and the restore verification last succeeded (ADR-0026).
 */
class BackupController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage-users');

        $kinds = [
            BackupRun::KIND_BACKUP => 'messages.backup_last_backup',
            BackupRun::KIND_VERIFICATION => 'messages.backup_last_verification',
        ];

        if (config('backup.offsite.enabled')) {
            $kinds = [
                BackupRun::KIND_BACKUP => $kinds[BackupRun::KIND_BACKUP],
                BackupRun::KIND_OFFSITE => 'messages.backup_last_offsite',
                BackupRun::KIND_VERIFICATION => $kinds[BackupRun::KIND_VERIFICATION],
            ];
        }

        $rows = [];

        foreach ($kinds as $kind => $label) {
            $rows[] = [
                'label' => __($label),
                'success' => BackupRun::lastSuccess($kind),
                'failure' => BackupRun::latestFailure($kind),
            ];
        }

        return view('admin.backups.index', ['rows' => $rows]);
    }
}

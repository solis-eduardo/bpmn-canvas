<?php

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * SPIKE ONLY (#209). Replaces the git-backed VersionRepository so the app runs
 * in php-wasm, where there is no git binary and no proc_open. It just writes the
 * content to the diagrams disk and reports no Version history.
 */
class StubVersionRepository extends VersionRepository
{
    public function initialize(Diagram $diagram, User $author): void {}

    public function save(Diagram $diagram, User $author, ?string $content = null, ?string $message = null, bool $allowEmpty = false): ?string
    {
        if ($content !== null) {
            Storage::disk('diagrams')->put($diagram->disk_path, $content);
            $diagram->clearLockDraft();
        }

        return null;
    }

    public function list(Diagram $diagram): array
    {
        return [];
    }
}

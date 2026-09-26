<?php

namespace App\Models;

use App\Repositories\VersionRepository;
use Database\Factories\ShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

#[Fillable(['diagram_id', 'expires_at', 'allow_download', 'revoked_at', 'created_by', 'pinned_version_id'])]
class Share extends Model
{
    /** @use HasFactory<ShareFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'allow_download' => 'boolean',
        ];
    }

    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * A pinned Share always serves the content of one specific Version; an
     * unpinned Share follows the Diagram's latest Version (default,
     * current behaviour) — see CONTEXT.md, "Share".
     */
    public function isPinned(): bool
    {
        return $this->pinned_version_id !== null;
    }

    /**
     * The Diagram content this Share serves: the pinned Version's content
     * when pinned, otherwise whatever is currently on disk (the latest
     * Version) — reuses VersionRepository::get(), the same access path
     * DiagramController's Version content endpoint already uses, instead of
     * duplicating Version-reading logic in each caller.
     */
    public function content(VersionRepository $versions): string
    {
        if ($this->isPinned()) {
            return $versions->get($this->diagram, $this->pinned_version_id);
        }

        return Storage::disk('diagrams')->get($this->diagram->disk_path);
    }

    public function showUrl(): string
    {
        return URL::temporarySignedRoute(
            'shares.show',
            $this->signatureExpiration(),
            ['share' => $this->id],
        );
    }

    public function downloadUrl(): ?string
    {
        if (! $this->allow_download) {
            return null;
        }

        return URL::temporarySignedRoute(
            'shares.download',
            $this->signatureExpiration(),
            ['share' => $this->id, 'format' => 'bpmn'],
        );
    }

    /**
     * The URL signature isn't the share's expiration mechanism: isValid()
     * handles that via expires_at/revoked_at. That's why the signature lasts
     * far longer than any valid expires_at (max 60 days).
     */
    private function signatureExpiration(): \DateTimeInterface
    {
        return now()->addYears(10);
    }
}

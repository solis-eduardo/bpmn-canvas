<?php

namespace App\Models;

use App\Models\Scopes\ActiveUserScope;
use App\Repositories\VersionRepository;
use Database\Factories\ShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['diagram_id', 'expires_at', 'allow_download', 'revoked_at', 'created_by', 'pinned_version_id', 'expiry_warned_at'])]
class Share extends Model
{
    /** @use HasFactory<ShareFactory> */
    use HasFactory;

    /** Length of the random public-link token; the `token` column is 64 wide. */
    public const TOKEN_LENGTH = 48;

    protected static function booted(): void
    {
        static::creating(function (Share $share) {
            $share->token ??= Str::random(self::TOKEN_LENGTH);
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'allow_download' => 'boolean',
            'expiry_warned_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScope(ActiveUserScope::class);
    }

    /**
     * Shares neither revoked nor expired — the query-level equivalent of
     * isValid().
     *
     * @param  Builder<Share>  $query
     * @return Builder<Share>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * True while this Share is still active (not revoked, not expired), its
     * expiration falls inside the given lead time, and it hasn't already
     * been warned about — the query `ShareExpiryWarning` scans with, kept
     * here so the "due for a warning" rule lives next to `isValid()`
     * instead of being reimplemented as a raw query in the command.
     */
    public function isDueForExpiryWarning(\DateTimeInterface $leadTimeCutoff): bool
    {
        return $this->isValid()
            && $this->expiry_warned_at === null
            && $this->expires_at->lessThanOrEqualTo($leadTimeCutoff);
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
        return route('shares.show', ['share' => $this->token]);
    }

    public function downloadUrl(): ?string
    {
        if (! $this->allow_download) {
            return null;
        }

        return route('shares.download', ['share' => $this->token, 'format' => 'bpmn']);
    }
}

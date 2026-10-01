<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Repositories\DiagramRepository;
use App\Repositories\ShareRepository;
use App\Repositories\VersionRepository;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Public Shares of a Diagram (CONTEXT.md, "Share"). Only the Diagram Owner
 * creates and revokes them; that is DiagramPolicy::manageShares' call.
 */
class ShareService
{
    public function __construct(
        private readonly ShareRepository $shares,
        private readonly DiagramRepository $diagrams,
        private readonly VersionRepository $versions,
    ) {}

    /**
     * @throws ValidationException when $pinnedVersionId isn't a Version of the Diagram
     */
    public function create(Diagram $diagram, User $creator, int $days = 7, bool $allowDownload = false, ?string $pinnedVersionId = null): Share
    {
        if ($pinnedVersionId !== null && ! collect($this->versions->list($diagram))->contains('id', $pinnedVersionId)) {
            throw ValidationException::withMessages([
                'pinned_version_id' => 'The selected version does not exist.',
            ]);
        }

        return $this->shares->create([
            'diagram_id' => $diagram->id,
            'expires_at' => now()->addDays($days),
            'allow_download' => $allowDownload,
            'created_by' => $creator->id,
            'pinned_version_id' => $pinnedVersionId,
        ]);
    }

    /**
     * @return Collection<int, Share>
     */
    public function listActive(Diagram $diagram): Collection
    {
        return $this->shares->activeFor($diagram);
    }

    public function revoke(Share $share): Share
    {
        return $this->shares->revoke($share);
    }

    /**
     * What the Share serves: the pinned Version's content when pinned,
     * otherwise whatever is currently on disk (the latest Version).
     */
    public function contentFor(Share $share): string
    {
        if ($share->isPinned()) {
            return $this->versions->get($share->diagram, $share->pinned_version_id);
        }

        return $this->diagrams->contentOf($share->diagram);
    }

    public function showUrlFor(Share $share): string
    {
        return URL::temporarySignedRoute('shares.show', $this->signatureExpiration(), ['share' => $share->id]);
    }

    public function downloadUrlFor(Share $share): ?string
    {
        if (! $share->allow_download) {
            return null;
        }

        return URL::temporarySignedRoute(
            'shares.download',
            $this->signatureExpiration(),
            ['share' => $share->id, 'format' => 'bpmn'],
        );
    }

    /**
     * The URL signature isn't the Share's expiration mechanism: expires_at
     * and revoked_at are. That's why the signature outlives any valid
     * expires_at (max 60 days).
     */
    private function signatureExpiration(): DateTimeInterface
    {
        return now()->addYears(10);
    }
}

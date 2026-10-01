<?php

namespace App\Repositories;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\DiagramOldSlug;
use App\Models\User;
use App\Support\Slugger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Keeps the Diagram's database row and its `.bpmn` file on disk consistent
 * with each other. No disk or transaction terms should leak out of this
 * class: callers only ever see "create", "move", and "remove" a Diagram.
 */
class DiagramRepository
{
    public function find(int $id): ?Diagram
    {
        return Diagram::find($id);
    }

    public function findBySlugOrFail(string $slug): Diagram
    {
        return Diagram::where('slug', $slug)->firstOrFail();
    }

    /**
     * Resolves a Slug to its Diagram, falling back to the old Slugs a rename
     * left behind. Null if neither matches.
     */
    public function findBySlugOrOldSlug(string $slug): ?ResolvedDiagram
    {
        $diagram = Diagram::where('slug', $slug)->first();

        if ($diagram) {
            return new ResolvedDiagram($diagram, viaOldSlug: false);
        }

        $old = DiagramOldSlug::with('diagram')->where('old_slug', $slug)->first();

        return $old?->diagram ? new ResolvedDiagram($old->diagram, viaOldSlug: true) : null;
    }

    /**
     * The $limit most recently modified Diagrams $user can open.
     *
     * @return Collection<int, Diagram>
     */
    public function recentFor(User $user, int $limit = 5): Collection
    {
        return Diagram::query()
            ->openableBy($user)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The Diagram list $user is allowed to see, ready for sorting and
     * pagination. The visibility rule is always applied first, so no filter
     * can surface a hidden Diagram. `owner` and `collaborator` describe the
     * User's relation to the Diagram, so both together are a union; every
     * other criterion narrows further.
     *
     * @return Builder<Diagram>
     */
    public function visibleListQuery(User $user, DiagramListCriteria $criteria): Builder
    {
        return Diagram::with(['author', 'owner', 'lockedBy', 'tags', 'workspace'])
            ->openableBy($user)
            ->when($criteria->withoutWorkspace, fn (Builder $query) => $query->whereNull('workspace_id'))
            ->when($criteria->workspaceId !== null, fn (Builder $query) => $query->where('workspace_id', $criteria->workspaceId))
            ->when($criteria->search !== '', fn (Builder $query) => $query->search($criteria->search))
            ->when($criteria->owner || $criteria->collaborator, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->when($criteria->owner, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query->ownedBy($user)))
                    ->when($criteria->collaborator, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query->collaboratedBy($user)))
            ))
            ->when($criteria->locked, fn (Builder $query) => $query->lockedNow())
            ->when($criteria->recent, fn (Builder $query) => $query->recentlyModified())
            ->when($criteria->tag !== '', fn (Builder $query) => $query->taggedWith($criteria->tag));
    }

    /**
     * Diagrams outside any Workspace that $user can see exist but can't
     * open (not theirs, not collaborated on) — the "discover" list. An
     * Administrator already sees everything in the main list, so gets none.
     *
     * @return Builder<Diagram>
     */
    public function discoveryQuery(User $user, string $search = ''): Builder
    {
        $query = Diagram::with([
            'owner',
            'collaborators' => fn ($collaborators) => $collaborators->where('user_id', $user->id),
        ]);

        if ($user->is_admin) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereNull('workspace_id')
                ->where('owner_id', '!=', $user->id)
                ->whereDoesntHave('collaborators', fn ($collaborators) => $collaborators
                    ->where('user_id', $user->id)
                    ->where('status', Collaborator::STATUS_APPROVED));
        }

        return $query->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * Generates a unique slug from a free-form name, normalizing it and
     * appending a short alphanumeric suffix on collision with the current
     * slug of any Diagram or with a reserved old slug.
     */
    public function uniqueSlug(string $name, ?int $ignoreDiagramId = null): string
    {
        $base = Slugger::slugify($name);
        $slug = $base;
        $attempts = 0;
        $maxAttempts = 20;

        while ($this->slugIsTaken($slug, $ignoreDiagramId)) {
            if (++$attempts > $maxAttempts) {
                $slug = $base.'-'.Str::lower(Str::random(12));

                break;
            }

            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    private function slugIsTaken(string $slug, ?int $ignoreDiagramId): bool
    {
        $current = Diagram::where('slug', $slug)
            ->when($ignoreDiagramId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreDiagramId));

        return $current->exists() || DiagramOldSlug::where('old_slug', $slug)->exists();
    }

    /**
     * Creates the Diagram row and writes $initialContent to disk. If the
     * write fails, the created row is rolled back (a Diagram never ends up
     * without a corresponding file).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $initialContent): Diagram
    {
        $diagram = Diagram::create($data);

        try {
            // The "diagrams" disk is configured with `throw => false`, so a
            // failed write reports itself by returning false rather than
            // throwing. Turn that into a real exception so the catch below
            // (and any caller further up) can react to it.
            $this->assertDiskSucceeded(
                Storage::disk('diagrams')->put($diagram->disk_path, $initialContent),
                "Could not write the Diagram file to disk: {$diagram->disk_path}"
            );
        } catch (\Throwable $e) {
            $diagram->delete();

            throw $e;
        }

        return $diagram;
    }

    /**
     * Renames a Diagram: moves the file on disk (if the Slug changed),
     * records the old Slug as a permanent redirect, and updates the row, all
     * within a transaction. If the transaction fails, the already-moved file
     * is moved back to its original place.
     */
    public function move(Diagram $diagram, string $newTitle, string $newSlug): Diagram
    {
        $oldSlug = $diagram->slug;
        $file = basename($diagram->disk_path);
        $slugChanged = $newSlug !== $oldSlug;

        if ($slugChanged) {
            // Same reasoning as create(): `throw => false` means a failed
            // move reports itself via a false return, not an exception.
            // Surface it explicitly — nothing has touched the database yet,
            // so there's nothing to compensate, just a clean failure.
            $this->assertDiskSucceeded(
                Storage::disk('diagrams')->move($oldSlug, $newSlug),
                "Could not move the Diagram directory from {$oldSlug} to {$newSlug}."
            );
        }

        try {
            DB::transaction(function () use ($diagram, $oldSlug, $newSlug, $file, $newTitle, $slugChanged) {
                if ($slugChanged) {
                    DiagramOldSlug::create([
                        'diagram_id' => $diagram->id,
                        'old_slug' => $oldSlug,
                    ]);
                }

                $diagram->update([
                    'title' => $newTitle,
                    'slug' => $newSlug,
                    'disk_path' => $newSlug.'/'.$file,
                ]);
            });
        } catch (\Throwable $e) {
            if ($slugChanged) {
                Storage::disk('diagrams')->move($newSlug, $oldSlug);
            }

            throw $e;
        }

        return $diagram;
    }

    /**
     * Removes the Diagram row and its corresponding directory on disk.
     */
    public function remove(Diagram $diagram): void
    {
        $slug = $diagram->slug;

        $diagram->delete();

        Storage::disk('diagrams')->deleteDirectory($slug);
    }

    /**
     * The "diagrams" disk is configured with `throw => false`, so a failed
     * disk operation reports itself by returning false rather than
     * throwing. Turn that into a real exception so callers can react to it
     * the same way they would to any other failure.
     */
    private function assertDiskSucceeded(bool $succeeded, string $message): void
    {
        if (! $succeeded) {
            throw new RuntimeException($message);
        }
    }
}

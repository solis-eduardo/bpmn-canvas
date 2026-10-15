<?php

namespace App\Http\Controllers;

use App\Exceptions\LockUnavailableException;
use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Rules\BpmnXml;
use App\Services\ActivityLogger;
use App\Services\LockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * A Diagram's Lock as one REST resource (CONTEXT.md, "Lock"; ADR-0011):
 * every verb is a single authorize() followed by a single LockService call.
 */
class LockController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly UserRepository $users,
        private readonly LockService $locks,
        private readonly ActivityLogger $activity,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('lock.view', $diagram);

        return response()->json($this->locks->state($diagram)->toArray());
    }

    /**
     * Acquires the Lock for the Editor that has just loaded (ADR-0003). Only
     * acquires: a Lock held by someone else is refused (423) and nothing is
     * requested on the holder's behalf. Requesting is {@see requestLock()}.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('lock.acquire', $diagram);

        if (! $this->locks->acquire($diagram, $request->user())) {
            // Reloaded: a free Lock taken by someone else a moment ago isn't
            // known to this request until the row is read again.
            throw LockUnavailableException::heldByOther($diagram->refresh());
        }

        return response()->json(['state' => 'acquired']);
    }

    /**
     * Asks for a Lock held by someone else: notifies the holder and answers
     * "waiting". Also acquires when the Lock is free.
     */
    public function requestLock(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('lock.acquire', $diagram);

        return response()->json($this->locks->openOrRequest($diagram, $request->user())->toArray());
    }

    /**
     * Heartbeat. Also reports, on the same response, who requested the Lock
     * since the last one — the Editor piggybacks its "someone wants the Lock"
     * notice on this poll (issue #9).
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        abort_unless(Gate::allows('lock.renew', $diagram), 403, __('messages.error_lock_lost'));

        return response()->json([
            'heartbeat' => true,
            'lock_requested_by' => $this->locks->heartbeat(
                $diagram,
                $request->user(),
                $this->usableDraft($request->input('draft')),
            )?->name,
        ]);
    }

    /**
     * The Heartbeat's draft if it is usable, null otherwise. Renewing the
     * Lock is the Heartbeat's job and carrying the draft is a bonus, so an
     * unusable draft is dropped rather than refused: a 422 would stop the
     * Lock being renewed and expire it under a holder who is actively
     * editing — and the Editor only reacts to 403/423, so they'd never be
     * told.
     */
    private function usableDraft(mixed $draft): ?string
    {
        if (! is_string($draft)) {
            return null;
        }

        return Validator::make(['draft' => $draft], ['draft' => [new BpmnXml]])->fails() ? null : $draft;
    }

    /**
     * Hands the Lock to another User, answering a request or not (ADR-0011).
     */
    public function transfer(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $newHolder = $this->users->find($data['user_id']);

        $this->authorize('lock.transfer', [$diagram, $newHolder]);

        $this->locks->transfer($diagram, $newHolder);

        return response()->json($this->locks->state($diagram)->toArray());
    }

    /**
     * Releases the Lock. `save_draft` keeps the holder's unsaved content as a
     * provisional Version first. Only the holder's own request may carry newer
     * content (`draft`); anyone else's saves what the server already holds.
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('lock.release', $diagram);

        $data = $request->validate(['save_draft' => ['sometimes', 'boolean']]);

        // Releasing a live Lock held by someone else is the Administrator's force-release.
        $forcedHolder = $diagram->hasActiveLock() && ! $diagram->isLockedBy($request->user()) ? $diagram->lockedBy : null;

        DB::transaction(function () use ($request, $diagram, $data, $forcedHolder): void {
            $this->locks->release(
                $diagram,
                (bool) ($data['save_draft'] ?? false),
                $diagram->isLockedBy($request->user()) ? $this->usableDraft($request->input('draft')) : null,
            );

            if ($forcedHolder !== null) {
                $this->activity->record('lock.force_released', $forcedHolder, diagram: $diagram, before: ['holder' => $forcedHolder->name]);
            }
        });

        return response()->json(['unlocked' => true]);
    }
}

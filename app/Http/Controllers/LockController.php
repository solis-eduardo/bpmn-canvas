<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\UserRepository;
use App\Rules\BpmnXml;
use App\Services\LockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
    ) {}

    public function show(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('lock.view', $diagram);

        return response()->json($this->locks->state($diagram)->toArray());
    }

    /**
     * Acquires the Lock when it is free, or registers a request and answers
     * "waiting" while someone else holds it.
     */
    public function store(Request $request, string $slug): JsonResponse
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

        $data = $request->validate([
            'draft' => ['nullable', 'string', new BpmnXml],
        ]);

        return response()->json([
            'heartbeat' => true,
            'lock_requested_by' => $this->locks->heartbeat($diagram, $request->user(), $data['draft'] ?? null)?->name,
        ]);
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

    public function destroy(string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('lock.release', $diagram);

        $this->locks->release($diagram);

        return response()->json(['unlocked' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Models\ActivityLog;
use App\Repositories\ActivityLogFilters;
use App\Repositories\ActivityLogRepository;
use App\Repositories\DiagramRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The entries of a Diagram's Activity panel in the Editor: the log entries
 * about that Diagram, newest first, a page at a time (CONTEXT.md, "Activity
 * Log"; ADR-0025). Read as JSON by public/assets/activity.js.
 */
class DiagramActivityController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly ActivityLogRepository $activity,
    ) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('viewActivity', $diagram);

        $page = $this->activity->search(ActivityLogFilters::fromRequest($request), $diagram);
        $viewer = $request->user();

        return response()->json([
            'entries' => $page->getCollection()->map(fn (ActivityLog $entry) => [
                'id' => $entry->id,
                'created_at' => $entry->created_at->toIso8601String(),
                'actor' => $entry->actor_name ?? __('messages.activity_system'),
                'action' => __('messages.activity_action_'.str_replace('.', '_', $entry->action)),
                'subject' => $entry->subject_label,
                'changes' => $entry->changes($viewer),
            ])->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}

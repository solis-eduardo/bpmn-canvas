<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\ActivityLogFilters;
use App\Repositories\ActivityLogRepository;
use App\Repositories\DiagramRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A Diagram's Activity tab: the log entries about that Diagram (CONTEXT.md,
 * "Activity Log"; ADR-0025).
 */
class DiagramActivityController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly ActivityLogRepository $activity,
    ) {}

    public function index(Request $request, string $slug): View
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('viewActivity', $diagram);

        $filters = ActivityLogFilters::fromRequest($request);

        return view('diagrams.activity', [
            'diagram' => $diagram,
            'filters' => $filters,
            'entries' => $this->activity->search($filters, $diagram)->appends($request->query()),
        ]);
    }
}

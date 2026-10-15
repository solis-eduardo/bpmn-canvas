<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\ActivityLogFilters;
use App\Repositories\ActivityLogRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The global activity log, for Administrators (CONTEXT.md, "Activity Log";
 * ADR-0025).
 */
class ActivityController extends Controller
{
    public function __construct(private readonly ActivityLogRepository $activity) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-users');

        $filters = ActivityLogFilters::fromRequest($request);

        return view('admin.activity', [
            'filters' => $filters,
            'entries' => $this->activity->search($filters)->appends($request->query()),
        ]);
    }
}

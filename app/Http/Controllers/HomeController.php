<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Repositories\DiagramListCriteria;
use App\Repositories\DiagramRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\Notifications\NotificationPresenter;
use App\Services\Notifications\NotificationSearch;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class HomeController
{
    /**
     * Items shown per type in the grouped search results; a "see all" link
     * leads to the filtered listing for the rest (issue #152).
     */
    private const RESULTS_PER_TYPE = 5;

    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly WorkspaceRepository $workspaces,
        private readonly NotificationSearch $notificationSearch,
        private readonly NotificationPresenter $presenter,
    ) {}

    /**
     * The home page. With a "q" term it shows only the grouped global
     * search (the default content is hidden): Diagrams (ADR-0013 rules, shared with the Diagram list),
     * Workspaces the User may see, and the User's own notifications.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));
        $diagrams = $search === '' ? $this->diagrams->recentFor($user) : collect();
        $results = null;

        if ($search !== '') {
            $criteria = new DiagramListCriteria(search: $search);
            $diagramQuery = $this->diagrams->searchResultsQuery($user, $criteria);
            $diagramTotal = (clone $diagramQuery)->count();
            $diagramItems = $diagramQuery
                ->orderBy('diagrams.title')
                ->orderBy('diagrams.id')
                ->limit(self::RESULTS_PER_TYPE)
                ->get();

            $discoverableIds = array_fill_keys(
                $this->diagrams->discoveryQuery($user, $search)
                    ->whereIn('diagrams.id', $diagramItems->modelKeys())
                    ->pluck('diagrams.id')
                    ->all(),
                true,
            );
            $diagramItems->each(
                fn (Diagram $diagram) => $diagram->setAttribute('is_discoverable', isset($discoverableIds[$diagram->id]))
            );

            $workspaces = $this->workspaces->matchingName($user, $search, self::RESULTS_PER_TYPE);

            $notifications = $this->notificationSearch->matching($user, $search);

            $results = [
                'diagrams' => ['items' => $diagramItems, 'total' => $diagramTotal],
                'workspaces' => $workspaces,
                'notifications' => [
                    'items' => $notifications->take(self::RESULTS_PER_TYPE)->map(fn (DatabaseNotification $notification) => [
                        'message' => $this->presenter->messageFor($notification),
                        'url' => route('notifications.read', $notification->id),
                    ])->values(),
                    'total' => $notifications->count(),
                ],
            ];
        }

        return view('home', compact('diagrams', 'search', 'results'));
    }
}

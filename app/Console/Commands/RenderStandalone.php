<?php

namespace App\Console\Commands;

use App\Http\Middleware\SetLocale;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\DiagramListCriteria;
use App\Repositories\ListSort;
use App\Support\Ui;
use Illuminate\Console\Command;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/**
 * Renders the standalone build's page from the same Blade layout, Editor
 * components and Diagram listing as the server build (ADR-0019), once per
 * language, so the two builds share one markup. standalone/main.js picks the
 * language's page, fills in the rows from the browser's storage and wires the
 * Editor.
 */
class RenderStandalone extends Command
{
    private const ROW_TOKENS = ['title' => '__title__', 'slug' => '__slug__'];

    protected $signature = 'standalone:render {--path= : Directory to write to (default: standalone/pages)}';

    protected $description = 'Render the standalone build pages (one per language) from the shared Blade views';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('standalone/pages');
        File::ensureDirectoryExists($path);
        $original = app()->getLocale();

        Ui::renderingStandalone();
        View::share('errors', new ViewErrorBag);

        try {
            foreach (SetLocale::SUPPORTED_LOCALES as $locale) {
                app()->setLocale($locale);
                File::put("{$path}/{$locale}.html", $this->page());
            }
        } finally {
            Ui::renderingStandalone(false);
            app()->setLocale($original);
        }

        $this->info('Standalone pages rendered.');

        return self::SUCCESS;
    }

    private function page(): string
    {
        return view('standalone.index', [
            'list' => $this->listData(collect()),
            'rowTemplate' => $this->rowTemplate(),
        ])->render();
    }

    /** The one row of the listing, as markup with tokens main.js fills in per Diagram. */
    private function rowTemplate(): string
    {
        $diagram = (new Diagram(['title' => self::ROW_TOKENS['title'], 'slug' => self::ROW_TOKENS['slug']]))
            ->setRelation('author', new User(['name' => '']))
            ->setRelation('owner', new User(['name' => '']))
            ->setRelation('workspace', null)
            ->setRelation('tags', collect())
            ->setAttribute('is_discoverable', false);

        $html = view('diagrams.partials.list', $this->listData(collect([$diagram])))->render();
        preg_match('~<tbody[^>]*>(.*?)</tbody>~s', $html, $body);

        return trim($body[1]);
    }

    /**
     * What DiagramController::index() hands the listing, for a browser that
     * stores only the signed-out User's own Diagrams.
     *
     * @return array<string, mixed>
     */
    private function listData($diagrams): array
    {
        return [
            'diagrams' => new LengthAwarePaginator($diagrams, $diagrams->count(), max(1, $diagrams->count())),
            'search' => '',
            'selectedTags' => [],
            'ownerId' => null,
            'authorId' => null,
            'users' => [],
            'filters' => ['owner' => false, 'collaborator' => false],
            'sort' => new ListSort(DiagramListCriteria::SORT_COLUMNS, array_key_first(DiagramListCriteria::SORT_COLUMNS), 'asc'),
            'workspaceFilter' => 'all',
            'workspaces' => collect(),
            'tagNames' => [],
        ];
    }
}

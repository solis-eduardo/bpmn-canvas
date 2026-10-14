<?php

namespace App\Console\Commands;

use App\Http\Middleware\SetLocale;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Renders the standalone build's pages by running the server build's own
 * routes (ADR-0019) against a throwaway in-memory database and a local User,
 * so every page is the real Blade view, byte for byte, with nothing in the
 * views made aware of the standalone build. One set per language;
 * standalone/main.js picks the page for the route, fills in the Diagrams from
 * the browser's storage and wires the Editor.
 *
 * The Diagram the pages are rendered for is a placeholder: its Slug and title
 * are tokens (`__slug__`, `__title__`) main.js replaces with the real ones.
 */
class RenderStandalone extends Command
{
    /** The origin the rendered links point at; main.js treats it as "this page". */
    public const ORIGIN = 'http://standalone.invalid';

    public const SLUG_TOKEN = '__slug__';

    public const TITLE_TOKEN = '__title__';

    /** Pages that exist before any Diagram does, by page name. */
    private const PAGES = [
        'home' => '/home',
        'library' => '/diagrams',
        'create' => '/diagrams/create',
        'workspaces' => '/workspaces',
        'users' => '/admin/users',
        'account' => '/account',
    ];

    protected $signature = 'standalone:render {--path= : Directory to write to (default: standalone/pages)}';

    protected $description = 'Render the standalone build pages (one per language) from the server routes';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('standalone/pages');
        File::deleteDirectory($path);

        $scratch = sys_get_temp_dir().'/standalone-render-'.Str::random(8);
        $restore = $this->isolate($scratch);

        try {
            $this->migrate();
            $user = $this->signIn();

            foreach ($this->locales() as $locale => $language) {
                foreach (self::PAGES as $name => $uri) {
                    $this->write($path, $locale, $name, $this->render($uri, $language));
                }
            }

            $diagram = $this->createPlaceholderDiagram($user);

            foreach ($this->locales() as $locale => $language) {
                // Before the Editor, which takes the placeholder's Lock for the local User.
                $this->write($path, $locale, 'library-row', $this->withRow($this->render('/diagrams', $language)));
                $this->write($path, $locale, 'edit', $this->render("/diagrams/{$diagram->slug}/edit", $language));
                $this->write($path, $locale, 'editor', $this->render("/diagrams/{$diagram->slug}", $language));
                $diagram->refresh()->releaseLock();
            }
        } finally {
            $restore();
            File::deleteDirectory($scratch);
        }

        $this->info('Standalone pages rendered.');

        return self::SUCCESS;
    }

    /**
     * Points the app at an in-memory database, an empty Diagram disk and
     * array-backed session and cache for the render, so nothing of the real
     * installation is read or written. Returns what puts everything back.
     */
    private function isolate(string $scratch): \Closure
    {
        $settings = [
            'database.default', 'cache.default', 'session.driver', 'queue.default',
            'bpmn.version_store', 'filesystems.disks.diagrams.root',
        ];
        $original = array_combine($settings, array_map(config(...), $settings));
        $request = request();

        File::ensureDirectoryExists($scratch);
        File::put("{$scratch}/hot", 'http://127.0.0.1:5173');

        config([
            'database.connections.standalone' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
            'database.default' => 'standalone',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'bpmn.version_store' => 'database',
            'filesystems.disks.diagrams.root' => "{$scratch}/diagrams",
        ]);
        DB::purge('standalone');
        Storage::forgetDisk('diagrams');
        // The pages are rendered for the browser's markup only: the dev-server tags @vite
        // writes into <head> are never used, so no built assets are needed.
        Vite::useHotFile("{$scratch}/hot");
        URL::forceRootUrl(self::ORIGIN);

        return function () use ($original, $request): void {
            config($original);
            // Each rendered page replaced the current request with its own.
            app()->instance('request', $request);
            URL::setRequest($request);
            DB::purge('standalone');
            Storage::forgetDisk('diagrams');
            Vite::useHotFile(public_path('hot'));
            URL::forceRootUrl(null);
            Auth::forgetGuards();
        };
    }

    private function migrate(): void
    {
        Artisan::call('migrate', ['--force' => true, '--database' => 'standalone']);
    }

    /** The browser's one User: an Administrator, so every screen of the server build opens. */
    private function signIn(): User
    {
        $user = (new User)->forceFill([
            'name' => 'Local',
            'email' => 'local@standalone.invalid',
            'password' => Str::random(40),
            'is_admin' => true,
            'timezone' => 'UTC',
        ]);
        $user->save();

        Auth::shouldUse('web');
        Auth::guard('web')->setUser($user);

        return $user;
    }

    private function createPlaceholderDiagram(User $user): Diagram
    {
        $diagram = app(DiagramRepository::class)->create([
            'title' => self::TITLE_TOKEN,
            'slug' => self::SLUG_TOKEN,
            'author_id' => $user->id,
            'owner_id' => $user->id,
            'disk_path' => self::SLUG_TOKEN.'/diagram.bpmn',
        ], Diagram::emptyBpmn());

        app(VersionRepository::class)->initialize($diagram, $user);

        return $diagram;
    }

    /** @return array<string, string> locale => Accept-Language */
    private function locales(): array
    {
        return array_combine(SetLocale::SUPPORTED_LOCALES, array_map(fn (string $locale): string => str_replace('_', '-', $locale), SetLocale::SUPPORTED_LOCALES));
    }

    private function render(string $uri, string $language): string
    {
        $request = Request::create(self::ORIGIN.$uri, 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => $language]);
        $response = app(Kernel::class)->handle($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("Rendering {$uri} answered {$response->getStatusCode()}.");
        }

        return $response->getContent();
    }

    /**
     * The listing with the placeholder Diagram's row: main.js clones that row
     * for each stored Diagram, so the row stays the server's own markup.
     */
    private function withRow(string $listing): string
    {
        if (! preg_match('~<tbody[^>]*>\s*<tr>.*?</tr>\s*</tbody>~s', $listing)) {
            throw new RuntimeException('The Diagram listing has no row to use as a template.');
        }

        return $listing;
    }

    private function write(string $path, string $locale, string $name, string $html): void
    {
        File::ensureDirectoryExists("{$path}/{$locale}");
        File::put("{$path}/{$locale}/{$name}.html", $html);
    }
}

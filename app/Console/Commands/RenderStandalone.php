<?php

namespace App\Console\Commands;

use App\Support\Ui;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Renders the standalone build's page from the same Blade layout and Editor
 * components as the server build (ADR-0019), so the two share one markup.
 * The text stays as `data-i18n` keys that standalone/main.js translates.
 */
class RenderStandalone extends Command
{
    protected $signature = 'standalone:render {--path= : File to write (default: standalone/index.html)}';

    protected $description = 'Render the standalone build page from the shared Blade views';

    public function handle(): int
    {
        Ui::renderingStandalone();

        try {
            $html = view('standalone.index')->render();
        } finally {
            Ui::renderingStandalone(false);
        }

        File::put($this->option('path') ?: base_path('standalone/index.html'), $html);
        $this->info('Standalone page rendered.');

        return self::SUCCESS;
    }
}

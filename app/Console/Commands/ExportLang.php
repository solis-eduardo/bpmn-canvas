<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/**
 * Exports the `lang/` strings the shared Editor core uses to one JSON file per
 * locale (ADR-0019), so the core gets them through `t(key)` instead of from
 * `data-*` attributes. `lang/` stays the only source of truth: the keys come
 * from the `t('…')` calls in the core's source, so using a new key in the core
 * is all it takes. A key ending in a dot (`t('messages.group.' + name)`) takes
 * the whole group. The standalone build's own scripts and page are scanned too
 * (`t('…')` and `data-i18n="…"` attributes), so the one JSON serves both builds.
 */
class ExportLang extends Command
{
    /** The shared core's modules, relative to the base path. */
    public const CORE_SOURCES = [
        'public/assets/editor-core.js',
        'public/assets/lint-ui.js',
        'public/assets/save-conflict.js',
        'public/assets/versions.js',
        'public/assets/version-history.js',
        'public/assets/diff.js',
        'public/assets/common.js',
    ];

    /** The standalone build's sources: scripts and the HTML shell, relative to the base path. */
    public const STANDALONE_GLOBS = ['standalone/*.js', 'standalone/*.html'];

    /** `t('key')` calls and `data-i18n="key"` / `data-i18n-title="key"` attributes. */
    public const KEY_PATTERN = "/\\bt\\(\\s*'([A-Za-z0-9_.]+)'|\\bdata-i18n(?:-[a-z-]+)?=\"([A-Za-z0-9_.]+)\"/";

    protected $signature = 'lang:export {--path= : Directory to write to (default: public/assets/lang)}';

    protected $description = 'Export the lang/ strings used by the shared Editor core to one JSON file per locale';

    public function handle(): int
    {
        $keys = $this->coreKeys();
        $locales = $this->locales();
        $path = $this->option('path') ?: public_path('assets/lang');
        File::ensureDirectoryExists($path);

        foreach ($locales as $locale) {
            $messages = [];

            foreach ($keys as $key) {
                $name = rtrim($key, '.');
                $translation = Lang::get($name, [], $locale);

                if ($translation === $name) {
                    $this->warn("No translation for '{$key}' in '{$locale}'.");

                    continue;
                }

                $messages += is_array($translation)
                    ? Arr::dot($translation, $name.'.')
                    : [$key => $translation];
            }

            ksort($messages);
            File::put("{$path}/{$locale}.json", json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }

        $this->info(count($keys).' key(s) exported for '.count($locales).' locale(s).');

        return self::SUCCESS;
    }

    /** Absolute paths of every file scanned for keys. @return list<string> */
    public static function sources(): array
    {
        $standalone = array_merge(...array_map(fn ($glob) => glob(base_path($glob)) ?: [], self::STANDALONE_GLOBS));

        return array_merge(array_map(base_path(...), self::CORE_SOURCES), $standalone);
    }

    /** @return list<string> */
    private function coreKeys(): array
    {
        $keys = [];

        foreach (self::sources() as $source) {
            preg_match_all(self::KEY_PATTERN, File::get($source), $matches);
            $keys = array_merge($keys, array_filter(array_merge($matches[1], $matches[2])));
        }

        return array_values(array_unique($keys));
    }

    /** @return list<string> */
    private function locales(): array
    {
        return array_map('basename', File::directories(lang_path()));
    }
}

<?php

namespace App\Support;

/**
 * Versioned URLs for the hand-written files in `public/assets/`.
 *
 * Each URL carries a hash of the file's content, so it changes exactly when the
 * file does and nginx can cache `/assets/` for a long time without a deploy ever
 * leaving a browser on stale code. ES modules import each other by relative
 * path (`./common.js`), which the browser resolves to the unversioned URL; the
 * import map from `importMap()` rewrites those to the versioned URLs.
 */
class PublicAssets
{
    /** @var array<string, string|null> */
    private array $versions = [];

    public function __construct(
        private readonly string $directory,
        private readonly string $baseUrl,
    ) {}

    /**
     * @param  string  $path  Relative to `public/assets/`, e.g. `editor.js`.
     */
    public function url(string $path): string
    {
        $url = $this->unversionedUrl($path);
        $version = $this->version($path);

        return $version === null ? $url : $url.'?v='.$version;
    }

    /**
     * Unversioned module URL => versioned module URL, for every top-level `.js`.
     *
     * @return array<string, string>
     */
    public function importMap(): array
    {
        $map = [];

        foreach (glob($this->directory.'/*.js') ?: [] as $file) {
            $name = basename($file);
            $map[$this->unversionedUrl($name)] = $this->url($name);
        }

        ksort($map);

        return $map;
    }

    /**
     * The import map document, safe to print inside a `<script>` element.
     *
     * @param  array<string, string>  $extraImports  Further specifier => URL entries.
     */
    public function importMapJson(array $extraImports = []): string
    {
        return json_encode(
            ['imports' => $this->importMap() + $extraImports],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    private function unversionedUrl(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function version(string $path): ?string
    {
        if (! array_key_exists($path, $this->versions)) {
            $file = $this->directory.'/'.ltrim($path, '/');
            $hash = is_file($file) ? md5_file($file) : false;

            $this->versions[$path] = $hash === false ? null : substr($hash, 0, 12);
        }

        return $this->versions[$path];
    }
}

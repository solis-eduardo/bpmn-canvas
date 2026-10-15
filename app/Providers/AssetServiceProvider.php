<?php

namespace App\Providers;

use App\Support\PublicAssets;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped so each request resolves its own base URL and hashes each file once.
        $this->app->scoped(PublicAssets::class, fn () => new PublicAssets(public_path('assets'), asset('assets')));
    }

    public function boot(): void
    {
        // `@assetUrl('editor.js')`: content-versioned URL of a file in public/assets/.
        Blade::directive('assetUrl', fn (string $expression) => "<?php echo e(app(\\App\\Support\\PublicAssets::class)->url({$expression})); ?>");
    }
}

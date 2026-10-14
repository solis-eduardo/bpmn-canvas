<?php

// Spike-only patches applied to the staged copy of the app (never to the repo itself).
// Usage: php patch-stage.php <stage-dir>
$stage = $argv[1];

function patch(string $file, string $search, string $replace): void
{
    $s = file_get_contents($file);
    if (! str_contains($s, $search)) {
        fwrite(STDERR, "patch anchor not found in $file\n");
        exit(1);
    }
    file_put_contents($file, str_replace($search, $replace, $s));
}

// 1. Sub-path hosting (GitHub Pages project site). The shell sends X-App-Base (e.g. /bpmn-canvas/app);
//    making it the SCRIPT_NAME directory lets Symfony derive the base URL, so routes and generated
//    URLs work under the prefix.
patch(
    "$stage/public/index.php",
    "define('LARAVEL_START', microtime(true));",
    "define('LARAVEL_START', microtime(true));\n\n"
    ."if (isset(\$_SERVER['HTTP_X_APP_BASE'])) {\n"
    ."    \$_SERVER['SCRIPT_NAME'] = \$_SERVER['PHP_SELF'] = \$_SERVER['HTTP_X_APP_BASE'].'/index.php';\n"
    .'}'
);

// 2. The shell page embeds the app in an <iframe>, which the app's CSP forbids (frame-ancestors 'none').
patch(
    "$stage/app/Http/Middleware/AddContentSecurityPolicy.php",
    "\"frame-ancestors 'none'\"",
    "\"frame-ancestors 'self'\""
);

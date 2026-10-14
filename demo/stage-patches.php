<?php

// Build-time patches for the staged copy of the app (demo/.stage). They never touch the repository.
// Usage: php stage-patches.php <stage-dir>
//
// Each patch fails the build when its anchor is missing, so a change in the app cannot silently
// leave the demo unpatched.

$stage = $argv[1] ?? '';

if ($stage === '' || ! is_dir($stage)) {
    fwrite(STDERR, "usage: php stage-patches.php <stage-dir>\n");
    exit(1);
}

function patch(string $file, string $search, string $replace): void
{
    $contents = file_get_contents($file);

    if ($contents === false || ! str_contains($contents, $search)) {
        fwrite(STDERR, "patch anchor not found in $file\n");
        exit(1);
    }

    file_put_contents($file, str_replace($search, $replace, $contents));
}

// 1. Sub-path hosting: the GitHub Pages project site serves the app under /bpmn-canvas/app/. The
//    shell sends X-App-Base with that prefix; using it as the script name lets Laravel derive the
//    base URL, so routes and generated links carry the prefix.
patch(
    "$stage/public/index.php",
    "define('LARAVEL_START', microtime(true));",
    "define('LARAVEL_START', microtime(true));\n\n"
    ."if (isset(\$_SERVER['HTTP_X_APP_BASE'])) {\n"
    ."    \$_SERVER['SCRIPT_NAME'] = \$_SERVER['PHP_SELF'] = \$_SERVER['HTTP_X_APP_BASE'].'/index.php';\n"
    .'}'
);

// 2. The shell embeds the app in an <iframe>, which the app's CSP forbids ('none'). Only the shell,
//    on the same origin, may frame it.
patch(
    "$stage/app/Http/Middleware/AddContentSecurityPolicy.php",
    "\"frame-ancestors 'none'\"",
    "\"frame-ancestors 'self'\""
);

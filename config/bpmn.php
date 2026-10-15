<?php

return [
    'admin_name' => env('ADMIN_NAME', 'Administrator'),
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // Name of the Keycloak role/claim (realm_access.roles) that grants the Administrator role.
    'keycloak_admin_role' => env('KEYCLOAK_ADMIN_ROLE', 'bpmn-admin'),

    // Minutes without a Heartbeat after which a Lock expires and is released (ADR-0003).
    'lock_ttl_minutes' => (int) env('LOCK_TTL_MINUTES', 3),

    // Where Version history is kept: "git" (one Git repository per Diagram, the
    // default) or "database" (rows in diagram_versions, no subprocesses; ADR-0018).
    'version_store' => env('VERSION_STORE', 'git'),

    // Seconds a writer waits for a Diagram's Version history lock before giving
    // up with a retryable 503 (ADR-0018, issue #242). At least 1: 0 or a negative
    // value would fail every save that finds the lock taken, without waiting.
    'version_lock_wait_seconds' => max(1, (int) env('VERSION_LOCK_WAIT_SECONDS', 30)),

    // Browser demo affordances (ADR-0018): demo sign-in, banner and reset.
    // Off by default; only .env.demo turns it on.
    'demo_mode' => (bool) env('DEMO_MODE', false),

    // Whether the scheduled backup runs. The browser demo has no Postgres to
    // dump and no scheduler, so .env.demo turns it off.
    'backups_enabled' => (bool) env('BACKUPS_ENABLED', true),

    // Server-side PNG/SVG/PDF rendering: headless bpmn-js in Chromium, run as
    // a Node subprocess (docker/renderer, issue #250).
    'renderer' => [
        'node_binary' => env('RENDERER_NODE_BINARY', 'node'),
        'script' => env('RENDERER_SCRIPT', base_path('docker/renderer/render.mjs')),
        // Null lets the script find a Chromium on the usual paths.
        'chromium_path' => env('RENDERER_CHROMIUM_PATH'),
        'timeout_seconds' => max(5, (int) env('RENDERER_TIMEOUT_SECONDS', 60)),
    ],
];

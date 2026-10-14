<?php

return [
    'admin_name' => env('ADMIN_NAME', 'Administrator'),
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // Name of the Keycloak role/claim (realm_access.roles) that grants the Administrator role.
    'keycloak_admin_role' => env('KEYCLOAK_ADMIN_ROLE', 'bpmn-admin'),

    // Minutes without a Heartbeat after which a Lock expires and is released (ADR-0003).
    'lock_ttl_minutes' => (int) env('LOCK_TTL_MINUTES', 5),

    // Where Version history is kept: "git" (one Git repository per Diagram, the
    // default) or "database" (rows in diagram_versions, no subprocesses; ADR-0018).
    'version_store' => env('VERSION_STORE', 'git'),

    // Browser demo affordances (ADR-0018): demo sign-in, banner and reset.
    // Off by default; only .env.demo turns it on.
    'demo_mode' => (bool) env('DEMO_MODE', false),

    // Whether the scheduled backup runs. The browser demo has no Postgres to
    // dump and no scheduler, so .env.demo turns it off.
    'backups_enabled' => (bool) env('BACKUPS_ENABLED', true),
];

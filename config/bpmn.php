<?php

return [
    'admin_name' => env('ADMIN_NAME', 'Administrator'),
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // Name of the Keycloak role/claim (realm_access.roles) that grants the Administrator role.
    'keycloak_admin_role' => env('KEYCLOAK_ADMIN_ROLE', 'bpmn-admin'),

    // Minutes without a Heartbeat after which a Lock expires and is released (ADR-0003).
    'lock_ttl_minutes' => (int) env('LOCK_TTL_MINUTES', 5),
];

<?php

return [
    'admin_name' => env('ADMIN_NAME', 'Administrador'),
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // Nome da role/claim do Keycloak (realm_access.roles) que concede o papel de Administrador.
    'keycloak_admin_role' => env('KEYCLOAK_ADMIN_ROLE', 'bpmn-admin'),
];

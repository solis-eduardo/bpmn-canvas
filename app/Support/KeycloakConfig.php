<?php

namespace App\Support;

/**
 * Keycloak is considered configured once the OAuth client credentials and
 * the realm base URL it redirects to are all present (see
 * config/services.php, sourced from KEYCLOAK_* env vars).
 */
final class KeycloakConfig
{
    public static function isConfigured(): bool
    {
        return filled(config('services.keycloak.client_id'))
            && filled(config('services.keycloak.client_secret'))
            && filled(config('services.keycloak.base_url'));
    }
}

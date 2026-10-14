<?php

namespace App\Support;

use SocialiteProviders\Keycloak\Provider;

/**
 * Socialite's Keycloak provider, extended to keep the OpenID Connect `id_token`
 * from the token exchange. Socialite discards the token response, but
 * RP-initiated logout needs the ID token as `id_token_hint`.
 */
class KeycloakProvider extends Provider
{
    private ?string $idToken = null;

    /**
     * @param  string  $code
     * @return array<string, mixed>|null
     */
    public function getAccessTokenResponse($code)
    {
        $response = parent::getAccessTokenResponse($code);

        $idToken = is_array($response) ? ($response['id_token'] ?? null) : null;
        $this->idToken = is_string($idToken) && $idToken !== '' ? $idToken : null;

        return $response;
    }

    /**
     * The ID token of the last token exchange performed by this provider, if any.
     */
    public function idToken(): ?string
    {
        return $this->idToken;
    }
}

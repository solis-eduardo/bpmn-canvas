<?php

namespace Tests\Unit\Support;

use App\Support\KeycloakProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class KeycloakProviderTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $tokenResponses
     */
    private function providerReturning(array $tokenResponses): KeycloakProvider
    {
        config([
            'services.keycloak.client_id' => 'bpmn-client',
            'services.keycloak.client_secret' => 'bpmn-secret',
            'services.keycloak.base_url' => 'https://sso.example.com',
            'services.keycloak.realms' => 'solis',
        ]);

        $handler = new MockHandler(array_map(
            fn (array $body) => new Response(200, [], json_encode($body)),
            $tokenResponses,
        ));

        /** @var KeycloakProvider $provider */
        $provider = Socialite::driver('keycloak');
        $provider->setHttpClient(new Client(['handler' => HandlerStack::create($handler)]));

        return $provider;
    }

    public function test_token_exchange_keeps_the_id_token(): void
    {
        $provider = $this->providerReturning([
            ['access_token' => 'access-1', 'id_token' => 'id-token-1'],
        ]);

        $provider->getAccessTokenResponse('code-1');

        $this->assertSame('id-token-1', $provider->idToken());
    }

    public function test_id_token_is_cleared_when_the_next_token_exchange_has_none(): void
    {
        $provider = $this->providerReturning([
            ['access_token' => 'access-1', 'id_token' => 'id-token-1'],
            ['access_token' => 'access-2'],
        ]);

        $provider->getAccessTokenResponse('code-1');
        $provider->getAccessTokenResponse('code-2');

        $this->assertNull($provider->idToken());
    }
}

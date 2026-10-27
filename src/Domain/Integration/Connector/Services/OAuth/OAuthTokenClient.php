<?php

namespace Domain\Integration\Connector\Services\OAuth;

use Domain\Integration\Connector\Data\OAuth\OAuthTokenSetData;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthStateData;
use Domain\Integration\Connector\Enums\OAuthGrant;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;
use Throwable;

/**
 * Talks to an OAuth authorization server's token endpoint: trades an
 * authorization code for tokens (with the PKCE verifier) and refreshes an
 * expired access token.
 *
 * The token endpoint comes from metadata the (possibly hostile) server
 * published, not from a value we checked when the user typed it. Every
 * request therefore re-checks it is a public address and refuses redirects,
 * so a malicious server cannot point the credential-bearing POST at cloud
 * metadata, loopback or private ranges.
 */
class OAuthTokenClient
{
    /** How much of a rejected response body the error message keeps. */
    private const int ERROR_BODY_LENGTH = 200;

    public function __construct(
        private readonly McpOAuthRegistrar $registrar,
    ) {}

    /**
     * Trade the authorization code from the consent screen for tokens.
     */
    public function exchangeCode(PendingOAuthStateData $pending, string $code): OAuthTokenSetData
    {
        return $this->request(OAuthGrant::AuthorizationCode, $pending->tokenEndpoint, $this->withSecret([
            'grant_type' => OAuthGrant::AuthorizationCode->value,
            'code' => $code,
            'redirect_uri' => $this->registrar->redirectUri(),
            'client_id' => $pending->clientId,
            'code_verifier' => $pending->codeVerifier,
        ], $pending->clientSecret));
    }

    /**
     * Mint a new access token from a refresh token.
     */
    public function refresh(string $tokenEndpoint, string $refreshToken, ConnectorOAuthClient $client): OAuthTokenSetData
    {
        return $this->request(OAuthGrant::RefreshToken, $tokenEndpoint, $this->withSecret([
            'grant_type' => OAuthGrant::RefreshToken->value,
            'refresh_token' => $refreshToken,
            'client_id' => $client->client_id,
        ], $client->client_secret));
    }

    /**
     * @param  array<string, string>  $form
     */
    private function request(OAuthGrant $grant, string $tokenEndpoint, array $form): OAuthTokenSetData
    {
        try {
            $clientOptions = SafeUrl::pinnedClientOptions($tokenEndpoint);
        } catch (Throwable $e) {
            throw new McpOAuthException($grant->blockedMessage(), $e->getCode(), previous: $e);
        }

        try {
            $response = Http::asForm()
                ->withOptions($clientOptions)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($tokenEndpoint, $form);
        } catch (Throwable $e) {
            throw new McpOAuthException($grant->failedMessage($e->getMessage()), $e->getCode(), previous: $e);
        }

        if ($response->failed()) {
            throw new McpOAuthException($grant->rejectedMessage(
                $response->status(),
                Str::substr($response->body(), 0, self::ERROR_BODY_LENGTH),
            ));
        }

        $tokens = (array) $response->json();
        if (empty($tokens['access_token'])) {
            throw new McpOAuthException($grant->missingTokenMessage());
        }

        return OAuthTokenSetData::fromResponse($tokens);
    }

    /**
     * Confidential clients authenticate with their secret; public (PKCE-only)
     * clients send none.
     *
     * @param  array<string, string>  $form
     * @return array<string, string>
     */
    private function withSecret(array $form, ?string $clientSecret): array
    {
        if ($clientSecret) {
            $form['client_secret'] = $clientSecret;
        }

        return $form;
    }
}

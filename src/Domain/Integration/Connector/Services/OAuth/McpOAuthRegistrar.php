<?php

namespace Domain\Integration\Connector\Services\OAuth;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadata;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;
use Throwable;

/**
 * The OAuth client for the authorization server protecting an MCP endpoint:
 * the connector's own OAuth app from config (Settings → Connectors) when the
 * server has no dynamic registration, else one registered with RFC 7591 once
 * per `auth_server_url` and reused by every agent that later connects it.
 */
class McpOAuthRegistrar
{
    /**
     * The client to use for the given auth server.
     *
     * Three paths:
     *   1. The server does not support dynamic registration and the connector
     *      has an OAuth app the admin registered (Settings → Connectors): a
     *      client built from config on every call and never stored, so a
     *      rotated secret applies at once. The stored row is keyed by auth
     *      server, which several connectors share (Gmail, Calendar and Drive
     *      all use Google's), so each must bring its own app.
     *   2. We've already registered with this server (RFC 7591) — return
     *      that row.
     *   3. Otherwise register dynamically, and keep the row for the next
     *      connection.
     */
    public function clientFor(OAuthServerMetadata $metadata, ?ConnectorData $connector = null): ConnectorOAuthClient
    {
        if (! $metadata->supportsDynamicRegistration()) {
            if ($connector instanceof ConnectorData && $connector->hasPreRegisteredOAuth()) {
                return $this->preRegistered($metadata, $connector);
            }

            throw new McpOAuthException(
                'This MCP server requires a pre-registered OAuth client. Dynamic Client Registration is not supported.'
            );
        }

        $existing = $this->findByAuthServer($metadata->authServerUrl);

        if ($existing instanceof ConnectorOAuthClient) {
            return $existing;
        }

        $payload = [
            // Shown to the user on the provider's consent screen.
            'client_name' => 'ChatVia.ai',
            'redirect_uris' => [$this->redirectUri()],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none', // public client; PKCE only
            'application_type' => 'web',
        ];

        try {
            // SSRF guard: registrationEndpoint comes from upstream-discovered
            // metadata. Re-validate, pin the checked address and disable
            // redirects before POSTing.
            $clientOptions = SafeUrl::pinnedClientOptions($metadata->registrationEndpoint);

            $response = Http::withOptions($clientOptions)->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($metadata->registrationEndpoint, $payload);
        } catch (Throwable $e) {
            throw new McpOAuthException('Dynamic client registration failed: '.$e->getMessage(), $e->getCode(), previous: $e);
        }

        if ($response->failed()) {
            throw new McpOAuthException(
                "Dynamic client registration was rejected ({$response->status()}): ".
                Str::substr($response->body(), 0, 200)
            );
        }

        $body = $response->json();
        if (! is_array($body) || empty($body['client_id'])) {
            throw new McpOAuthException('Dynamic client registration returned no client_id.');
        }

        return ConnectorOAuthClient::create([
            'auth_server_url' => $metadata->authServerUrl,
            'client_id' => (string) $body['client_id'],
            'client_secret' => $body['client_secret'] ?? null,
            'registration_access_token' => $body['registration_access_token'] ?? null,
            'registration_client_uri' => $body['registration_client_uri'] ?? null,
            'token_endpoint' => $metadata->tokenEndpoint,
            'authorization_endpoint' => $metadata->authorizationEndpoint,
            'metadata' => $this->publicRegistrationFields($body),
        ]);
    }

    /**
     * The OAuth app the admin registered for a connector, from config. Not
     * saved: each connection stores the id and secret it was made with.
     */
    private function preRegistered(OAuthServerMetadata $metadata, ConnectorData $connector): ConnectorOAuthClient
    {
        return new ConnectorOAuthClient()->forceFill([
            'auth_server_url' => $metadata->authServerUrl,
            'client_id' => $connector->oauthClientId ?? '',
            'client_secret' => $connector->oauthClientSecret,
            'registration_access_token' => null,
            'registration_client_uri' => null,
            'token_endpoint' => $metadata->tokenEndpoint,
            'authorization_endpoint' => $metadata->authorizationEndpoint,
            'metadata' => ['source' => 'pre_registered', 'connector_slug' => $connector->slug],
        ]);
    }

    /**
     * The `metadata` column is plain jsonb, while the secrets in a
     * registration response (client_secret, registration_access_token) have
     * their own encrypted columns. Keep only descriptive, non-secret fields
     * so no credential ends up in plain text.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function publicRegistrationFields(array $body): array
    {
        return ['source' => 'dynamic_registration'] + array_intersect_key($body, array_flip([
            'client_id_issued_at',
            'client_secret_expires_at',
            'client_name',
            'redirect_uris',
            'grant_types',
            'response_types',
            'token_endpoint_auth_method',
            'scope',
        ]));
    }

    /**
     * Where providers send the browser back after consent.
     */
    public function redirectUri(): string
    {
        return Str::rtrim(config('app.url'), '/').'/connectors/oauth/callback';
    }

    /**
     * The registered client for an authorization server — for background
     * token refreshes, which have no pending flow in hand. The registrar owns
     * every read of the registration cache.
     */
    public function findByAuthServer(string $authServerUrl): ?ConnectorOAuthClient
    {
        return ConnectorOAuthClient::query()->where('auth_server_url', $authServerUrl)->first();
    }
}

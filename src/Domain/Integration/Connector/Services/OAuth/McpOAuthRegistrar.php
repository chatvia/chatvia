<?php

namespace Domain\Integration\Connector\Services\OAuth;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Illuminate\Support\Facades\Http;
use Support\Http\SafeUrl;
use Throwable;

/**
 * RFC 7591 — dynamically registers ChatVia as an OAuth client with the
 * authorization server protecting an MCP endpoint. We register once per
 * `auth_server_url`; every agent that later connects the same service
 * reuses the cached `client_id`.
 */
class McpOAuthRegistrar
{
    /**
     * Look up or create a registered client for the given auth server.
     *
     * Three paths:
     *   1. We've already registered for this auth-server URL — return that row.
     *   2. Server supports RFC 7591 DCR — register dynamically.
     *   3. Server doesn't support DCR but the catalog carries pre-registered
     *      credentials (env-var driven, e.g. GitHub) — insert a synthetic
     *      row from those creds.
     */
    public function clientFor(OAuthServerMetadata $metadata, ?ConnectorData $connector = null): ConnectorOAuthClient
    {
        $existing = ConnectorOAuthClient::query()
            ->where('auth_server_url', $metadata->authServerUrl)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $metadata->supportsDynamicRegistration()) {
            if ($connector instanceof ConnectorData && $connector->hasPreRegisteredOAuth()) {
                return ConnectorOAuthClient::create([
                    'auth_server_url' => $metadata->authServerUrl,
                    'client_id' => (string) $connector->oauthClientId,
                    'client_secret' => $connector->oauthClientSecret,
                    'registration_access_token' => null,
                    'registration_client_uri' => null,
                    'token_endpoint' => $metadata->tokenEndpoint,
                    'authorization_endpoint' => $metadata->authorizationEndpoint,
                    'metadata' => ['source' => 'pre_registered', 'connector_slug' => $connector->slug],
                ]);
            }

            throw new McpOAuthException(
                'This MCP server requires a pre-registered OAuth client. Dynamic Client Registration is not supported.'
            );
        }

        $payload = [
            'client_name' => 'ChatVia',
            'redirect_uris' => [$this->redirectUri()],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none', // public client; PKCE only
            'application_type' => 'web',
        ];

        try {
            // SSRF guard: registrationEndpoint comes from upstream-discovered
            // metadata. Re-validate and disable redirects before POSTing.
            SafeUrl::assert($metadata->registrationEndpoint);

            $response = Http::withOptions(SafeUrl::clientOptions())->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($metadata->registrationEndpoint, $payload);
        } catch (Throwable $e) {
            throw new McpOAuthException('Dynamic client registration failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new McpOAuthException(
                "Dynamic client registration was rejected ({$response->status()}): ".
                mb_substr((string) $response->body(), 0, 200)
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

    public function redirectUri(): string
    {
        return rtrim(config('app.url'), '/').'/connectors/oauth/callback';
    }

    /**
     * Lookup helper for background flows (token refresh) that don't have a
     * PendingOAuthState in hand. Lives here so the flow service can stay
     * model-free and the registrar owns all DCR-cache access.
     */
    public function findByAuthServer(string $authServerUrl): ?ConnectorOAuthClient
    {
        return ConnectorOAuthClient::query()->where('auth_server_url', $authServerUrl)->first();
    }
}

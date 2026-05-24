<?php

namespace Domain\Connectors\Services\OAuth;

use Domain\Connectors\Data\ConnectorData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Owns the OAuth 2.1 + PKCE authorization-code lifecycle for MCP
 * connectors:
 *
 *   begin()    → discover, register (DCR), generate PKCE+state,
 *                stash pending state, return the upstream authorize URL.
 *   complete() → recover state, exchange code for tokens, probe tools/list,
 *                upsert the AgentMcpServer row.
 *
 * The pending-state cache lives in Redis with a 10-min TTL — long enough
 * for slow consent screens, short enough to bound the CSRF window.
 */
class McpOAuthFlow
{
    private const STATE_TTL_SECONDS = 600;

    public function __construct(
        private readonly McpOAuthDiscoverer $discoverer,
        private readonly McpOAuthRegistrar $registrar,
        private readonly McpCatalog $catalog,
    ) {}

    /**
     * Kick off the authorize redirect for a connector. Returns the URL the
     * user should be sent to. Caller is responsible for issuing the actual
     * HTTP redirect.
     */
    public function begin(Agent $agent, ConnectorData $connector): string
    {
        return $this->beginCommon(
            agent: $agent,
            mcpUrl: $connector->mcpUrl,
            connectorSlug: $connector->slug,
            customName: null,
            customSlug: null,
            connector: $connector,
        );
    }

    /**
     * Same as begin(), but for a user-added MCP server that doesn't have a
     * catalog entry. The custom name + slug are carried through the pending
     * state so the callback can persist a row with `connector_slug = null`.
     */
    public function beginCustom(Agent $agent, string $name, string $slug, string $mcpUrl): string
    {
        return $this->beginCommon(
            agent: $agent,
            mcpUrl: $mcpUrl,
            connectorSlug: null,
            customName: $name,
            customSlug: $slug,
            connector: null,
        );
    }

    private function beginCommon(
        Agent $agent,
        string $mcpUrl,
        ?string $connectorSlug,
        ?string $customName,
        ?string $customSlug,
        ?ConnectorData $connector,
    ): string {
        // Pre-flight: if the connector requires pre-registered OAuth but the
        // env vars haven't been filled in, fail fast with a helpful message
        // rather than discovering the auth server and failing later.
        if ($connector && $connector->authStrategy === 'oauth'
            && $connector->oauthAuthorizationEndpoint
            && empty($connector->oauthClientId)
        ) {
            throw new McpOAuthException(
                "{$connector->name} needs an OAuth App registered manually. ".
                'Create one at the service and set the OAuth client_id + client_secret in your environment.'
            );
        }

        $metadata = $this->discoverer->discover($mcpUrl, $connector);
        $client = $this->registrar->clientFor($metadata, $connector);

        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->codeChallengeFor($codeVerifier);
        $state = Str::random(48);

        $pending = new PendingOAuthState(
            agentId: $agent->id,
            connectorSlug: $connectorSlug,
            authServerUrl: $metadata->authServerUrl,
            tokenEndpoint: $client->token_endpoint,
            clientId: $client->client_id,
            codeVerifier: $codeVerifier,
            mcpUrl: $mcpUrl,
            clientSecret: $client->client_secret,
            scopes: $connector?->oauthScopes,
            customName: $customName,
            customSlug: $customSlug,
        );

        $this->stateStore()->put(
            $this->stateKey($state),
            $pending->toArray(),
            self::STATE_TTL_SECONDS,
        );

        $authorizeParams = [
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => $this->registrar->redirectUri(),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ];
        if ($connector?->oauthScopes) {
            $authorizeParams['scope'] = $connector->oauthScopes;
        }

        return $this->buildAuthorizeUrl($client->authorization_endpoint, $authorizeParams);
    }

    /**
     * Complete the round-trip from the upstream consent screen. Exchanges
     * the auth code for tokens, probes the server's tool catalog, and
     * persists the AgentMcpServer row.
     */
    public function complete(string $state, string $code): AgentMcpServer
    {
        $payload = $this->stateStore()->pull($this->stateKey($state));

        if (! is_array($payload)) {
            throw new McpOAuthException('This OAuth session has expired or was already used. Please try again.');
        }

        $pending = PendingOAuthState::fromArray($payload);

        $tokens = $this->exchangeCode($pending, $code);

        return DB::transaction(function () use ($pending, $tokens) {
            $isCustom = $pending->connectorSlug === null;

            $query = AgentMcpServer::query()->where('agent_id', $pending->agentId);
            $server = $isCustom
                ? $query->where('slug', $pending->customSlug)->whereNull('connector_slug')->first()
                : $query->where('connector_slug', $pending->connectorSlug)->first();

            $attrs = [
                'agent_id' => $pending->agentId,
                'connector_slug' => $pending->connectorSlug,
                'name' => $isCustom
                    ? (string) $pending->customName
                    : $this->displayName((string) $pending->connectorSlug),
                'slug' => $isCustom
                    ? (string) $pending->customSlug
                    : AgentMcpServer::makeSlug((string) $pending->connectorSlug),
                'url' => $pending->mcpUrl,
                'transport' => 'streamable_http',
                'auth_type' => 'bearer',
                'auth_value' => $tokens['access_token'],
                'headers' => null,
                'timeout_ms' => 30000,
                'enabled' => true,
                'last_error' => null,
                'oauth_authorization_server' => $pending->authServerUrl,
                'oauth_token_endpoint' => $pending->tokenEndpoint,
                'oauth_refresh_token' => $tokens['refresh_token'] ?? null,
                'oauth_expires_at' => isset($tokens['expires_in'])
                    ? now()->addSeconds((int) $tokens['expires_in'])
                    : null,
                'oauth_scopes' => $tokens['scope'] ?? null,
            ];

            if ($server) {
                $server->fill($attrs)->save();
            } else {
                $server = AgentMcpServer::create($attrs);
            }

            try {
                $this->catalog->refresh($server);
            } catch (Throwable $e) {
                $server->forceFill(['last_error' => $e->getMessage()])->save();
            }

            return $server->fresh();
        });
    }

    /**
     * Use the refresh token to mint a new access token. Persists the new
     * pair onto the AgentMcpServer row.
     */
    public function refresh(AgentMcpServer $server): void
    {
        if (! $server->oauth_token_endpoint || ! $server->oauth_refresh_token) {
            throw new McpOAuthException('This server has no refresh token; the user must reconnect.');
        }

        $client = $this->clientForServer($server);

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $server->oauth_refresh_token,
            'client_id' => $client->client_id,
        ];

        if ($client->client_secret) {
            $body['client_secret'] = $client->client_secret;
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['Accept' => 'application/json'])
                ->post($server->oauth_token_endpoint, $body);
        } catch (Throwable $e) {
            throw new McpOAuthException('Token refresh failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new McpOAuthException(
                'Token refresh was rejected ('.$response->status().'): '.
                mb_substr((string) $response->body(), 0, 200)
            );
        }

        $tokens = (array) $response->json();
        if (empty($tokens['access_token'])) {
            throw new McpOAuthException('Token refresh response had no access_token.');
        }

        $server->forceFill([
            'auth_value' => (string) $tokens['access_token'],
            // RFC 6749: many servers issue a fresh refresh token on every
            // refresh and invalidate the old one. Honour it if present.
            'oauth_refresh_token' => $tokens['refresh_token'] ?? $server->oauth_refresh_token,
            'oauth_expires_at' => isset($tokens['expires_in'])
                ? now()->addSeconds((int) $tokens['expires_in'])
                : null,
            'oauth_scopes' => $tokens['scope'] ?? $server->oauth_scopes,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCode(PendingOAuthState $pending, string $code): array
    {
        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->registrar->redirectUri(),
            'client_id' => $pending->clientId,
            'code_verifier' => $pending->codeVerifier,
        ];

        if ($pending->clientSecret) {
            $body['client_secret'] = $pending->clientSecret;
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['Accept' => 'application/json'])
                ->post($pending->tokenEndpoint, $body);
        } catch (Throwable $e) {
            throw new McpOAuthException('Token exchange failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new McpOAuthException(
                'The authorization server rejected the token exchange ('.$response->status().'): '.
                mb_substr((string) $response->body(), 0, 200)
            );
        }

        $tokens = (array) $response->json();
        if (empty($tokens['access_token'])) {
            throw new McpOAuthException('The authorization server returned no access_token.');
        }

        return $tokens;
    }

    /**
     * Look up the DCR client registration for a connected server (used when
     * we don't have a PendingOAuthState — i.e. background refreshes).
     */
    private function clientForServer(AgentMcpServer $server): \Domain\Connectors\Models\ConnectorOAuthClient
    {
        $client = $this->registrar->findByAuthServer((string) $server->oauth_authorization_server);

        if (! $client) {
            throw new McpOAuthException('No registered OAuth client found for '.$server->oauth_authorization_server);
        }

        return $client;
    }

    private function generateCodeVerifier(): string
    {
        // 32 random bytes → 43-char base64url, the minimum length RFC 7636
        // requires while staying well under the 128-char ceiling.
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function codeChallengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, string>  $params
     */
    private function buildAuthorizeUrl(string $endpoint, array $params): string
    {
        $separator = str_contains($endpoint, '?') ? '&' : '?';

        return $endpoint.$separator.http_build_query($params);
    }

    private function displayName(string $slug): string
    {
        $entry = config('connectors.'.$slug);

        return is_array($entry) && isset($entry['name']) ? (string) $entry['name'] : ucfirst($slug);
    }

    private function stateKey(string $state): string
    {
        return 'oauth:mcp:state:'.$state;
    }

    private function stateStore(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('cache.default'));
    }
}

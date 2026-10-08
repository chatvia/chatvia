<?php

namespace Domain\Integration\Connector\Services\OAuth;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\BringYourOwnOAuthData;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;
use Throwable;

/**
 * Owns the OAuth 2.1 + PKCE authorization-code lifecycle for MCP
 * connectors:
 *
 *   begin()    → discover, register (DCR), generate PKCE+state,
 *                stash pending state, return the upstream authorize URL.
 *   complete() → recover state, check it belongs to the caller, exchange
 *                code for tokens, probe tools/list, upsert the
 *                AgentMcpServer row under the workspace owner.
 *
 * The pending state is cached (encrypted) for 10 minutes — long enough for
 * slow consent screens, short enough to bound the CSRF window. It is bound
 * to the user and browser session that started the flow: `$sessionBinding`
 * is a random secret the HTTP layer keeps in the user's session and passes
 * to both begin and complete.
 */
class McpOAuthFlow
{
    private const STATE_TTL_SECONDS = 600;

    /** How long one token refresh may hold the per-server lock. */
    private const REFRESH_LOCK_SECONDS = 30;

    /** How long a caller waits for another worker's refresh to finish. */
    private const REFRESH_WAIT_SECONDS = 15;

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
    public function begin(User $user, ConnectorData $connector, string $sessionBinding): string
    {
        return $this->beginCommon(
            user: $user,
            sessionBinding: $sessionBinding,
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
    public function beginCustom(User $user, string $name, string $slug, string $mcpUrl, string $sessionBinding): string
    {
        return $this->beginCommon(
            user: $user,
            sessionBinding: $sessionBinding,
            mcpUrl: $mcpUrl,
            connectorSlug: null,
            customName: $name,
            customSlug: $slug,
            connector: null,
        );
    }

    /**
     * Kick off the authorize redirect for a connector whose OAuth client is
     * registered per-workspace (no global app / no DCR — e.g. Salesforce). The
     * caller has already resolved the workspace's client_id + concrete endpoints
     * + MCP URL, so we skip discovery and the registrar entirely and drive PKCE
     * straight off the supplied values. The client_id/secret ride through the
     * pending state and are persisted on the server row in complete() so the
     * background refresh can recover them.
     */
    public function beginWithClient(User $user, ConnectorData $connector, BringYourOwnOAuthData $byo, string $sessionBinding): string
    {
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->codeChallengeFor($codeVerifier);
        $state = Str::random(48);

        $pending = new PendingOAuthState(
            userId: $user->id,
            connectorSlug: $connector->slug,
            authServerUrl: $byo->authServerUrl,
            tokenEndpoint: $byo->tokenEndpoint,
            clientId: $byo->clientId,
            codeVerifier: $codeVerifier,
            mcpUrl: $byo->mcpUrl,
            clientSecret: $byo->clientSecret,
            scopes: $connector->oauthScopes,
            sessionBindingHash: PendingOAuthState::hashBinding($sessionBinding),
        );

        $this->stateStore()->put($this->stateKey($state), $pending->seal(), self::STATE_TTL_SECONDS);

        $authorizeParams = [
            'response_type' => 'code',
            'client_id' => $byo->clientId,
            'redirect_uri' => $this->registrar->redirectUri(),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ];
        if ($connector->oauthScopes) {
            $authorizeParams['scope'] = $connector->oauthScopes;
        }

        return $this->buildAuthorizeUrl($byo->authorizationEndpoint, $authorizeParams);
    }

    private function beginCommon(
        User $user,
        string $sessionBinding,
        string $mcpUrl,
        ?string $connectorSlug,
        ?string $customName,
        ?string $customSlug,
        ?ConnectorData $connector,
    ): string {
        // Pre-flight: if the connector requires pre-registered OAuth but the
        // env vars haven't been filled in, fail fast. This is an operator
        // misconfiguration, not a user error — the detailed message is for the
        // error tracker / local debug only; the controller reports it and shows
        // the user a generic failure rather than leaking our env setup.
        if ($connector instanceof ConnectorData && $connector->authStrategy === 'oauth'
            && $connector->oauthAuthorizationEndpoint
            && empty($connector->oauthClientId)
        ) {
            throw new ConnectorMisconfiguredException(
                "{$connector->name} needs an OAuth App registered manually. ".
                'Create one at the service and set the OAuth client_id + client_secret in the environment.'
            );
        }

        $metadata = $this->discoverer->discover($mcpUrl, $connector);

        // OAuth 2.1 makes PKCE mandatory. A server that does not advertise S256
        // could silently ignore our code_challenge, leaving a stolen code usable.
        if (! $metadata->supportsPkce()) {
            throw new McpOAuthException(
                'This service does not support the secure sign-in method (PKCE with S256) that '.config('chatvia.brand.name').' requires, so it cannot be connected.'
            );
        }

        $client = $this->registrar->clientFor($metadata, $connector);

        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->codeChallengeFor($codeVerifier);
        $state = Str::random(48);

        $pending = new PendingOAuthState(
            userId: $user->id,
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
            sessionBindingHash: PendingOAuthState::hashBinding($sessionBinding),
        );

        $this->stateStore()->put(
            $this->stateKey($state),
            $pending->seal(),
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
     * Complete the round-trip from the upstream consent screen. Checks the
     * state belongs to `$user` and their browser session, exchanges the auth
     * code for tokens, probes the server's tool catalog, and persists the
     * AgentMcpServer row under the workspace owner.
     */
    public function complete(User $user, string $sessionBinding, string $state, string $code): AgentMcpServer
    {
        $pending = PendingOAuthState::unseal($this->stateStore()->pull($this->stateKey($state)));

        if ($pending === null) {
            throw new McpOAuthException('This connection request has expired or was already used. Please try again.');
        }

        // Without this check, anyone could send a victim their own authorize
        // link: the victim's third-party tokens would then be saved in the
        // sender's workspace.
        if ($pending->userId !== $user->id || ! $pending->matchesSession($sessionBinding)) {
            throw new McpOAuthException(
                'This connection was started from a different account or browser. Please start it again from the Connectors page.'
            );
        }

        $tokens = $this->exchangeCode($pending, $code);

        // A connector is a workspace resource: key it to the owner so every
        // teammate and every workspace agent sees the same row.
        $ownerId = $user->workspace()->id;

        // Tokens must commit before the (remote HTTP, non-fatal) catalog refresh.
        $isCustom = $pending->connectorSlug === null;

        $query = AgentMcpServer::query()->where('user_id', $ownerId);

        $server = $isCustom
            ? $query->where('slug', $pending->customSlug)->whereNull('connector_slug')->first()
            : $query->where('connector_slug', $pending->connectorSlug)->first();

        $attrs = [
            'user_id' => $ownerId,
            'category' => $isCustom ? McpServerCategory::Custom : McpServerCategory::Integration,
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
            'timeout_ms' => 300000,
            'is_enabled' => true,
            'last_error' => null,
            'oauth_authorization_server' => $pending->authServerUrl,
            'oauth_token_endpoint' => $pending->tokenEndpoint,
            // Keep the client used for this connection on the row so the
            // background refresh can recover it without the shared,
            // auth-server-keyed registry (which can't hold per-org clients).
            'oauth_client_id' => $pending->clientId,
            'oauth_client_secret' => $pending->clientSecret,
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
    }

    /**
     * Refresh the access token if it is about to expire. Many providers
     * rotate refresh tokens, so two workers refreshing at once would burn
     * the token and disconnect the server. A per-server lock serialises the
     * refresh, and the staleness check is repeated after the lock is taken
     * because another worker may have refreshed in the meantime.
     */
    public function refreshIfStale(AgentMcpServer $server): void
    {
        if (! $server->accessTokenIsStale()) {
            return;
        }

        try {
            Cache::lock('mcp:refresh:'.$server->id, self::REFRESH_LOCK_SECONDS)
                ->block(self::REFRESH_WAIT_SECONDS, function () use ($server): void {
                    if ($server->exists) {
                        $server->refresh();
                    }

                    if ($server->accessTokenIsStale()) {
                        $this->refresh($server);
                    }
                });
        } catch (LockTimeoutException) {
            // Another worker still holds the lock; use whatever token it has
            // stored by now rather than racing it.
            if ($server->exists) {
                $server->refresh();
            }
        }
    }

    /**
     * Use the refresh token to mint a new access token. Persists the new
     * pair onto the AgentMcpServer row. Callers should go through
     * {@see refreshIfStale()}, which serialises concurrent refreshes.
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

        // SSRF guard: the token endpoint originates from attacker-served OAuth
        // metadata (not a user-typed, pre-validated value), so re-assert it is a
        // public address and disable redirects — mirroring every other
        // user-influenced fetch in the connector flow. Without this a malicious
        // auth-server can point token_endpoint at 169.254.169.254 / loopback /
        // RFC1918 and drive this background POST there.
        try {
            SafeUrl::assert((string) $server->oauth_token_endpoint);
        } catch (Throwable $e) {
            throw new McpOAuthException('Token refresh blocked: unsafe token endpoint.', previous: $e);
        }

        try {
            $response = Http::asForm()
                ->withOptions(SafeUrl::clientOptions())
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

        // SSRF guard — see refresh(). token_endpoint comes from the upstream
        // authorization-server metadata the (attacker-controlled) host returned,
        // so validate it against private/reserved ranges and disable redirects
        // before POSTing the credential-bearing exchange.
        try {
            SafeUrl::assert((string) $pending->tokenEndpoint);
        } catch (Throwable $e) {
            throw new McpOAuthException('Token exchange blocked: unsafe token endpoint.', previous: $e);
        }

        try {
            $response = Http::asForm()
                ->withOptions(SafeUrl::clientOptions())
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
    private function clientForServer(AgentMcpServer $server): ConnectorOAuthClient
    {
        // Prefer a client stored directly on the row (per-workspace BYO clients,
        // and anything connected after that column was added) over the shared
        // auth-server-keyed registry — which cannot represent per-org client_ids.
        if ($server->oauth_client_id) {
            $client = new ConnectorOAuthClient;
            $client->client_id = (string) $server->oauth_client_id;
            $client->client_secret = $server->oauth_client_secret;
            $client->token_endpoint = (string) $server->oauth_token_endpoint;
            $client->authorization_endpoint = '';
            $client->auth_server_url = (string) $server->oauth_authorization_server;

            return $client; // transient — only client_id/secret are read for refresh
        }

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

    private function stateStore(): Repository
    {
        return Cache::store(config('cache.default'));
    }
}

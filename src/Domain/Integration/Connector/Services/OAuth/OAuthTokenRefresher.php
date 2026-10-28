<?php

namespace Domain\Integration\Connector\Services\OAuth;

use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Contracts\AccessTokenRefresher;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Refreshes the access token of an OAuth-connected MCP server before it
 * expires.
 *
 * Many providers rotate refresh tokens: each refresh returns a new one and
 * invalidates the old. Two workers refreshing the same server at once would
 * therefore burn the token and disconnect the server. A per-server lock
 * serialises refreshes, and the staleness check is repeated once the lock is
 * held, because another worker may have refreshed in the meantime.
 */
class OAuthTokenRefresher implements AccessTokenRefresher
{
    /** How long one token refresh may hold the per-server lock. */
    private const int LOCK_SECONDS = 30;

    /** How long a caller waits for another worker's refresh to finish. */
    private const int WAIT_SECONDS = 15;

    public function __construct(
        private readonly OAuthTokenClient $tokens,
        private readonly McpOAuthRegistrar $registrar,
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function refreshIfStale(AgentMcpServer $server): void
    {
        if (! $server->accessTokenIsStale()) {
            return;
        }

        try {
            Cache::lock("mcp:refresh:{$server->id}", self::LOCK_SECONDS)
                ->block(self::WAIT_SECONDS, function () use ($server): void {
                    $this->reload($server);

                    if ($server->accessTokenIsStale()) {
                        $this->refresh($server);
                    }
                });
        } catch (LockTimeoutException) {
            // Another worker still holds the lock; use whatever token it has
            // stored by now rather than racing it.
            $this->reload($server);
        }
    }

    /**
     * Mint a new access token with the stored refresh token and save it.
     * Callers should go through {@see refreshIfStale()}, which serialises
     * concurrent refreshes.
     */
    public function refresh(AgentMcpServer $server): void
    {
        if (! $server->oauth_token_endpoint || ! $server->oauth_refresh_token) {
            throw new McpOAuthException('This server has no refresh token; the user must reconnect.');
        }

        $tokens = $this->tokens->refresh(
            $server->oauth_token_endpoint,
            $server->oauth_refresh_token,
            $this->clientFor($server),
            $server->url,
        );

        $server->forceFill([
            'auth_value' => $tokens->accessToken,
            // RFC 6749: a server may issue a new refresh token on every refresh
            // and invalidate the old one; keep the old one only when it didn't.
            'oauth_refresh_token' => $tokens->refreshToken ?? $server->oauth_refresh_token,
            'oauth_expires_at' => $tokens->expiresAt(),
            'oauth_scopes' => $tokens->scope ?? $server->oauth_scopes,
        ])->save();
    }

    /**
     * The OAuth client the server was connected with. A client stored on the
     * row (per-workspace clients, and every connection made since that column
     * exists) wins over the shared registry keyed by authorization server,
     * which cannot represent per-org client ids. For a connector's own OAuth
     * app (Settings → Connectors) the secret comes from config while the id
     * is unchanged, so a rotated secret applies to existing connections.
     */
    private function clientFor(AgentMcpServer $server): ConnectorOAuthClient
    {
        if ($server->oauth_client_id) {
            $connector = $server->connector_slug !== null ? $this->catalog->find($server->connector_slug) : null;
            $secret = $connector?->hasPreRegisteredOAuth() && $connector->oauthClientId === $server->oauth_client_id
                ? $connector->oauthClientSecret
                : $server->oauth_client_secret;

            // Transient: only the id and secret are read for a refresh.
            return new ConnectorOAuthClient()->forceFill([
                'client_id' => (string) $server->oauth_client_id,
                'client_secret' => $secret,
                'token_endpoint' => (string) $server->oauth_token_endpoint,
                'authorization_endpoint' => '',
                'auth_server_url' => (string) $server->oauth_authorization_server,
            ]);
        }

        return $this->registrar->findByAuthServer((string) $server->oauth_authorization_server)
            ?? throw new McpOAuthException("No registered OAuth client found for {$server->oauth_authorization_server}");
    }

    private function reload(AgentMcpServer $server): void
    {
        if ($server->exists) {
            $server->refresh();
        }
    }
}

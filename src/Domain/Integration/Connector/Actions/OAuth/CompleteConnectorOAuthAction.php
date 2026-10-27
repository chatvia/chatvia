<?php

namespace Domain\Integration\Connector\Actions\OAuth;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\OAuth\ConnectorOAuthCallbackData;
use Domain\Integration\Connector\Data\OAuth\OAuthTokenSetData;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthStateData;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\OAuth\OAuthStateStore;
use Domain\Integration\Connector\Services\OAuth\OAuthTokenClient;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Enums\McpTransport;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finishes a connector OAuth flow when the provider redirects back: recover
 * the pending flow, check it belongs to this user AND this browser session,
 * trade the code for tokens, and save the server under the workspace owner.
 * The tool catalog is then synced; a failure there is kept on the row rather
 * than undoing a connection that worked.
 */
class CompleteConnectorOAuthAction
{
    public function __construct(
        private readonly OAuthStateStore $states,
        private readonly OAuthTokenClient $tokens,
        private readonly ConnectorCatalog $catalog,
        private readonly SyncMcpServerCatalogAction $syncCatalog,
    ) {}

    public function execute(User $user, ConnectorOAuthCallbackData $callback): AgentMcpServer
    {
        $pending = $this->states->pull($callback->state);

        if (! $pending instanceof PendingOAuthStateData) {
            throw new McpOAuthException('This connection request has expired or was already used. Please try again.');
        }

        // Without this check, anyone could send a victim their own authorize
        // link: the victim's third-party tokens would then be saved in the
        // sender's workspace.
        if ($pending->userId !== $user->id || ! $pending->matchesSession($callback->sessionBinding)) {
            throw new McpOAuthException(
                'This connection was started from a different account or browser. Please start it again from the Connectors page.'
            );
        }

        // Tokens are saved before the catalog sync (a remote call that may fail).
        $server = $this->saveServer($user, $pending, $this->tokens->exchangeCode($pending, $callback->code));

        try {
            $this->syncCatalog->execute($server);
        } catch (Throwable $e) {
            $server->recordError($e->getMessage());
        }

        return $server->fresh();
    }

    /**
     * A connector is a workspace resource: it is keyed to the workspace so every
     * teammate and every agent of the workspace sees the same row. A repeat
     * connect updates the existing row.
     */
    private function saveServer(User $user, PendingOAuthStateData $pending, OAuthTokenSetData $tokens): AgentMcpServer
    {
        $workspace = $user->workspace();
        $isCustom = $pending->connectorSlug === null;

        $server = $isCustom
            ? $workspace->mcpServers()->custom()->where('slug', $pending->customSlug)->first()
            : $workspace->mcpServers()->where('connector_slug', $pending->connectorSlug)->first();

        $server ??= new AgentMcpServer;

        $server->fill([
            'workspace_id' => $workspace->id,
            'category' => $isCustom ? McpServerCategory::Custom : McpServerCategory::Integration,
            'connector_slug' => $pending->connectorSlug,
            'name' => $isCustom ? ($pending->customName ?? '') : $this->connectorName($pending->connectorSlug ?? ''),
            'slug' => $isCustom ? ($pending->customSlug ?? '') : AgentMcpServer::makeSlug($pending->connectorSlug ?? ''),
            'url' => $pending->mcpUrl,
            'transport' => McpTransport::StreamableHttp,
            'auth_type' => McpAuthType::Bearer,
            'auth_value' => $tokens->accessToken,
            'headers' => null,
            'timeout_ms' => AgentMcpServer::DEFAULT_TIMEOUT_MS,
            'is_enabled' => true,
            'last_error' => null,
            'oauth_authorization_server' => $pending->authServerUrl,
            'oauth_token_endpoint' => $pending->tokenEndpoint,
            // The client used for this connection stays on the row so the
            // background refresh can use it without the shared registry, which
            // is keyed by authorization server and cannot hold per-org clients.
            'oauth_client_id' => $pending->clientId,
            'oauth_client_secret' => $pending->clientSecret,
            'oauth_refresh_token' => $tokens->refreshToken,
            'oauth_expires_at' => $tokens->expiresAt(),
            'oauth_scopes' => $tokens->scope,
        ])->save();

        return $server;
    }

    private function connectorName(string $slug): string
    {
        return $this->catalog->find($slug)?->name ?? Str::ucfirst($slug);
    }
}

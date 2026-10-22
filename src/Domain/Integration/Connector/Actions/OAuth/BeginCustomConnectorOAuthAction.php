<?php

namespace Domain\Integration\Connector\Actions\OAuth;

use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;
use Domain\Integration\Connector\Services\OAuth\OAuthAuthorizationRedirect;
use Domain\Integration\Connector\Services\OAuth\OAuthClientDiscovery;
use Domain\Integration\Connector\Services\OAuth\Pkce;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Starts the OAuth 2.1 + PKCE flow for an MCP server the workspace adds by
 * URL (no catalog entry). The server's name and a slug that is free in the
 * workspace ride along in the pending state, so the callback can save the
 * row as a custom server (`connector_slug = null`) under the workspace owner.
 */
class BeginCustomConnectorOAuthAction
{
    public function __construct(
        private readonly OAuthClientDiscovery $clients,
        private readonly OAuthAuthorizationRedirect $redirect,
    ) {}

    public function execute(AddCustomConnectorData $data, string $sessionBinding): string
    {
        $client = $this->clients->forMcpUrl($data->url);

        return $this->redirect->start(new PendingOAuthState(
            userId: $data->user->id,
            connectorSlug: null,
            authServerUrl: $client->auth_server_url,
            tokenEndpoint: $client->token_endpoint,
            clientId: $client->client_id,
            codeVerifier: Pkce::verifier(),
            mcpUrl: $data->url,
            sessionBindingHash: PendingOAuthState::hashBinding($sessionBinding),
            clientSecret: $client->client_secret,
            customName: $data->name,
            customSlug: AgentMcpServer::uniqueSlugFor($data->user->workspace(), $data->name),
        ), $client->authorization_endpoint);
    }
}

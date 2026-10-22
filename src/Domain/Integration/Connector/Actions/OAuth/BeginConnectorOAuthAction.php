<?php

namespace Domain\Integration\Connector\Actions\OAuth;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Connector\Exceptions\ConnectorMisconfiguredException;
use Domain\Integration\Connector\Services\OAuth\OAuthAuthorizationRedirect;
use Domain\Integration\Connector\Services\OAuth\OAuthClientDiscovery;
use Domain\Integration\Connector\Services\OAuth\Pkce;

/**
 * Starts the OAuth 2.1 + PKCE flow for a catalog connector: find (or
 * register) our OAuth client with the connector's authorization server, park
 * the pending flow and return the provider's authorize URL. The flow is bound
 * to the user and to `$sessionBinding`, a secret the HTTP layer keeps in the
 * user's browser session; the callback must present both.
 */
class BeginConnectorOAuthAction
{
    public function __construct(
        private readonly OAuthClientDiscovery $clients,
        private readonly OAuthAuthorizationRedirect $redirect,
    ) {}

    public function execute(User $user, ConnectorData $connector, string $sessionBinding): string
    {
        $this->ensureConfigured($connector);

        $client = $this->clients->forMcpUrl($connector->mcpUrl, $connector);

        return $this->redirect->start(new PendingOAuthState(
            userId: $user->id,
            connectorSlug: $connector->slug,
            authServerUrl: $client->auth_server_url,
            tokenEndpoint: $client->token_endpoint,
            clientId: $client->client_id,
            codeVerifier: Pkce::verifier(),
            mcpUrl: $connector->mcpUrl,
            sessionBindingHash: PendingOAuthState::hashBinding($sessionBinding),
            clientSecret: $client->client_secret,
            scopes: $connector->oauthScopes,
        ), $client->authorization_endpoint);
    }

    /**
     * A connector wired for a pre-registered OAuth app whose credentials the
     * operator never set is a server misconfiguration, not a user error. The
     * detailed message is for error tracking only; the controller shows the
     * user a generic failure instead.
     */
    private function ensureConfigured(ConnectorData $connector): void
    {
        if ($connector->authStrategy === ConnectorAuthStrategy::Oauth
            && $connector->oauthAuthorizationEndpoint
            && empty($connector->oauthClientId)
        ) {
            throw new ConnectorMisconfiguredException(
                "{$connector->name} needs an OAuth App registered manually. ".
                'Create one at the service and set its client ID and secret in the admin panel (Settings → Connectors).'
            );
        }
    }
}

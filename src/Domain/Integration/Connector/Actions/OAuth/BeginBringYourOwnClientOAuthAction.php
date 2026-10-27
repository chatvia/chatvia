<?php

namespace Domain\Integration\Connector\Actions\OAuth;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\BringYourOwnOAuthData;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthStateData;
use Domain\Integration\Connector\Services\OAuth\OAuthAuthorizationRedirect;
use Domain\Integration\Connector\Services\OAuth\Pkce;

/**
 * Starts the OAuth 2.1 + PKCE flow for a connector whose OAuth client each
 * workspace registers itself (no global app, no dynamic registration — e.g.
 * a Salesforce External Client App). The workspace already supplied the
 * client id and the endpoints, so discovery and registration are skipped.
 * The client id and secret ride in the pending state and are saved on the
 * server row by the callback, so background refreshes can use them.
 */
class BeginBringYourOwnClientOAuthAction
{
    public function __construct(
        private readonly OAuthAuthorizationRedirect $redirect,
    ) {}

    public function execute(User $user, ConnectorData $connector, BringYourOwnOAuthData $client, string $sessionBinding): string
    {
        return $this->redirect->start(new PendingOAuthStateData(
            userId: $user->id,
            connectorSlug: $connector->slug,
            authServerUrl: $client->authServerUrl,
            tokenEndpoint: $client->tokenEndpoint,
            clientId: $client->clientId,
            codeVerifier: Pkce::verifier(),
            mcpUrl: $client->mcpUrl,
            sessionBindingHash: PendingOAuthStateData::hashBinding($sessionBinding),
            clientSecret: $client->clientSecret,
            scopes: $connector->oauthScopes,
        ), $client->authorizationEndpoint);
    }
}

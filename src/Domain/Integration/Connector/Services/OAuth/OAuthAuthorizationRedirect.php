<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Services\OAuth;

use Domain\Integration\Connector\Data\OAuth\PendingOAuthStateData;
use Illuminate\Support\Uri;

/**
 * The last step of every "start OAuth" path: park the pending flow under a
 * fresh `state` and build the provider's authorize URL (authorization code +
 * PKCE S256). The caller sends the browser there. The MCP server's address
 * goes along as the `resource` (RFC 8707), so the authorization server
 * binds the token to that server: a token meant for one server is then no
 * good to another one that pointed us at the same authorization server.
 */
class OAuthAuthorizationRedirect
{
    public function __construct(
        private readonly OAuthStateStore $states,
        private readonly McpOAuthRegistrar $registrar,
    ) {}

    public function start(PendingOAuthStateData $pending, string $authorizationEndpoint): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $pending->clientId,
            'redirect_uri' => $this->registrar->redirectUri(),
            'code_challenge' => Pkce::challenge($pending->codeVerifier),
            'code_challenge_method' => 'S256',
            'resource' => $pending->mcpUrl,
            'state' => $this->states->put($pending),
        ];

        if ($pending->scopes) {
            $params['scope'] = $pending->scopes;
        }

        return Uri::of($authorizationEndpoint)->withQuery($params)->value();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support\Integration\Connector;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;

/**
 * A connector OAuth flow that is waiting for its callback: the sealed pending
 * state cached the way BeginConnectorOAuthAction caches it, for a flow
 * started by a user in a browser session that holds $binding.
 */
final class PendingConnectorOAuth
{
    /**
     * Cache the pending state $state. A null connector slug is a custom
     * server, which carries its own name and slug.
     */
    public static function stash(string $state, User $starter, string $binding, ?string $connectorSlug = 'notion'): void
    {
        $pending = new PendingOAuthState(
            userId: $starter->id,
            connectorSlug: $connectorSlug,
            authServerUrl: 'https://auth.example.test',
            tokenEndpoint: 'https://auth.example.test/token',
            clientId: 'client-123',
            codeVerifier: 'verifier',
            mcpUrl: 'https://mcp.example.test/rpc',
            sessionBindingHash: PendingOAuthState::hashBinding($binding),
            customName: $connectorSlug === null ? 'My server' : null,
            customSlug: $connectorSlug === null ? 'my_server' : null,
        );

        cache()->put('oauth:mcp:state:'.$state, $pending->seal(), 600);
    }
}

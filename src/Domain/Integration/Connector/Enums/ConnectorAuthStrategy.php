<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How a workspace connects a catalog connector (`Connector::$authStrategy`):
 *
 *   - None  → no credentials; connecting is a single click.
 *   - Token → the workspace pastes an API token (and, for some presets, its
 *             own MCP server URL).
 *   - Oauth → an OAuth 2.1 + PKCE redirect to the provider's consent screen.
 *
 * None and Token connect in one synchronous request; Oauth needs the
 * redirect round-trip.
 */
#[TypeScript]
enum ConnectorAuthStrategy: string
{
    case None = 'none';
    case Token = 'token';
    case Oauth = 'oauth';

    /**
     * True when the connector can be connected in one request, without an
     * OAuth redirect.
     */
    public function connectsDirectly(): bool
    {
        return $this !== self::Oauth;
    }
}

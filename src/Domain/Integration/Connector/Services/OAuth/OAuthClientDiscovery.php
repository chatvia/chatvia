<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Services\OAuth;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;

/**
 * Finds the OAuth client to use for an MCP endpoint: discover the
 * authorization server that protects it, insist on PKCE S256, then reuse,
 * dynamically register or (for catalog presets without DCR) pre-register our
 * client with that server.
 */
class OAuthClientDiscovery
{
    public function __construct(
        private readonly McpOAuthDiscoverer $discoverer,
        private readonly McpOAuthRegistrar $registrar,
    ) {}

    public function forMcpUrl(string $mcpUrl, ?ConnectorData $connector = null): ConnectorOAuthClient
    {
        $metadata = $this->discoverer->discover($mcpUrl, $connector);

        // OAuth 2.1 makes PKCE mandatory. A server that does not advertise S256
        // could silently ignore our code_challenge, leaving a stolen code usable.
        if (! $metadata->supportsPkce()) {
            throw new McpOAuthException(
                'This service does not support the secure sign-in method (PKCE with S256) that ChatVia.ai requires, so it cannot be connected.'
            );
        }

        return $this->registrar->clientFor($metadata, $connector);
    }
}

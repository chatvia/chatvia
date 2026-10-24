<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog;

use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;

/**
 * A connector whose OAuth client is registered by each workspace (no global
 * app, no dynamic registration). The dashboard asks for the workspace's own
 * client id at connect time and the connector's own controller derives the
 * endpoints and MCP URL from the answers, so the catalog has no MCP URL.
 */
abstract class WorkspaceClientOAuthConnector extends Connector
{
    /** Space-separated scopes, as the provider expects them. */
    abstract public string $scopes { get; }

    /** The provider's guide to creating the workspace's OAuth client. */
    abstract public string $helpUrl { get; }

    #[\Override]
    public ConnectorAuthStrategy $authStrategy {
        get => ConnectorAuthStrategy::Oauth;
    }

    #[\Override]
    public string $mcpUrl {
        get => '';
    }

    #[\Override]
    protected function extraData(): array
    {
        return [
            'requiresClientId' => true,
            'oauthScopes' => $this->scopes,
            'tokenHelpUrl' => $this->helpUrl,
        ];
    }
}

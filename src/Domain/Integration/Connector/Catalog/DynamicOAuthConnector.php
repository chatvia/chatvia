<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog;

use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;

/**
 * A connector whose MCP server is an OAuth 2.1 server with Dynamic Client
 * Registration (RFC 7591): the connect flow discovers it and registers a
 * client on the first connection, so nothing is set up by hand.
 */
abstract class DynamicOAuthConnector extends Connector
{
    #[\Override]
    public ConnectorAuthStrategy $authStrategy {
        get => ConnectorAuthStrategy::Oauth;
    }
}

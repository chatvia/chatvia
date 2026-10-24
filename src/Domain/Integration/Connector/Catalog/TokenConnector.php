<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog;

use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Mcp\Enums\McpAuthType;

/**
 * A connector the workspace connects by pasting an API key, sent on every MCP
 * call (a bearer token unless {@see $authType} says otherwise). With
 * {@see $requiresUrl} the workspace also gives its own MCP server's address,
 * for services with no hosted server of their own.
 */
abstract class TokenConnector extends Connector
{
    /** The field label, e.g. "Perplexity API key". */
    abstract public string $tokenLabel { get; }

    /** Where the workspace finds its key. */
    abstract public string $tokenHelpUrl { get; }

    #[\Override]
    public ConnectorAuthStrategy $authStrategy {
        get => ConnectorAuthStrategy::Token;
    }

    public string $tokenPlaceholder {
        get => 'Your '.$this->tokenLabel;
    }

    public McpAuthType $authType {
        get => McpAuthType::Bearer;
    }

    /** Whether the workspace supplies the MCP server's address; the catalog URL is then empty. */
    public bool $requiresUrl {
        get => false;
    }

    #[\Override]
    protected function extraData(): array
    {
        return [
            'authType' => $this->authType->value,
            'tokenLabel' => $this->tokenLabel,
            'tokenHelpUrl' => $this->tokenHelpUrl,
            'tokenPlaceholder' => $this->tokenPlaceholder,
            'requiresUrl' => $this->requiresUrl,
        ];
    }
}

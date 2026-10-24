<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Google's hosted Gmail MCP server. Google has no dynamic client registration: the admin creates a Web OAuth client in Google
 * Cloud and enters it in Settings → Connectors. Gmail, Calendar and Drive each have their own
 * entry (the same client may go in all three); enable each API in the Cloud project.
 */
final class GmailConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'gmail'; }

    #[\Override]
    public string $name { get => 'Gmail'; }

    #[\Override]
    public string $description { get => 'Search messages, read threads, and draft replies from your Gmail inbox.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Communication; }

    #[\Override]
    public string $brandColor { get => 'EA4335'; }

    #[\Override]
    public int $priority { get => 10; }

    #[\Override]
    public string $mcpUrl { get => 'https://gmailmcp.googleapis.com/mcp/v1'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://accounts.google.com/o/oauth2/v2/auth'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://oauth2.googleapis.com/token'; }

    #[\Override]
    public string $scopes { get => 'https://www.googleapis.com/auth/gmail.readonly'; }

    #[\Override]
    public ?string $appConsoleUrl { get => 'https://console.cloud.google.com/apis/credentials'; }
}

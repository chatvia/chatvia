<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Google's hosted Drive MCP server. Google has no dynamic client registration: the admin creates a Web OAuth client in Google
 * Cloud and enters it in Settings → Connectors. Gmail, Calendar and Drive each have their own
 * entry (the same client may go in all three); enable each API in the Cloud project.
 */
final class GoogleDriveConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'google-drive'; }

    #[\Override]
    public string $name { get => 'Google Drive'; }

    #[\Override]
    public string $description { get => 'Read files and folders from your Google Drive.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '0F9D58'; }

    #[\Override]
    public int $priority { get => 40; }

    #[\Override]
    public string $mcpUrl { get => 'https://drivemcp.googleapis.com/mcp/v1'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://accounts.google.com/o/oauth2/v2/auth'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://oauth2.googleapis.com/token'; }

    #[\Override]
    public string $scopes { get => 'https://www.googleapis.com/auth/drive.readonly'; }

    #[\Override]
    public ?string $appConsoleUrl { get => 'https://console.cloud.google.com/apis/credentials'; }
}

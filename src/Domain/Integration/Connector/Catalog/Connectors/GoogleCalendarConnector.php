<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Google's hosted Calendar MCP server. Google has no dynamic client registration: the admin creates a Web OAuth client in Google
 * Cloud and enters it in Settings → Connectors. Gmail, Calendar and Drive each have their own
 * entry (the same client may go in all three); enable each API in the Cloud project.
 */
final class GoogleCalendarConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'google-calendar'; }

    #[\Override]
    public string $name { get => 'Google Calendar'; }

    #[\Override]
    public string $description { get => 'Read and create events across your Google Calendars.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '4285F4'; }

    #[\Override]
    public int $priority { get => 30; }

    #[\Override]
    public string $mcpUrl { get => 'https://calendarmcp.googleapis.com/mcp/v1'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://accounts.google.com/o/oauth2/v2/auth'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://oauth2.googleapis.com/token'; }

    #[\Override]
    public string $scopes { get => 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly'; }

    #[\Override]
    public ?string $appConsoleUrl { get => 'https://console.cloud.google.com/apis/credentials'; }
}

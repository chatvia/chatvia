<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * HubSpot's hosted MCP server: OAuth 2.1 + PKCE without open dynamic registration. The admin
 * creates an "MCP Auth App" in HubSpot (Settings → Integrations → MCP Auth Apps); the scopes
 * asked for must match those granted to that app.
 */
final class HubspotConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'hubspot'; }

    #[\Override]
    public string $name { get => 'HubSpot'; }

    #[\Override]
    public string $description { get => 'Read and update contacts, companies, deals, and tickets in your HubSpot CRM.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Sales; }

    #[\Override]
    public string $brandColor { get => 'FF7A59'; }

    #[\Override]
    public int $priority { get => 45; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.hubspot.com'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://app.hubspot.com/oauth/authorize'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://api.hubapi.com/oauth/v1/token'; }

    #[\Override]
    public string $scopes { get => 'crm.objects.contacts.read crm.objects.companies.read crm.objects.deals.read tickets'; }

    #[\Override]
    public ?string $appConsoleUrl { get => null; }
}

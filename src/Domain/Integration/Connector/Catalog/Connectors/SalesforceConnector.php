<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\WorkspaceClientOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Salesforce Hosted MCP Servers. The OAuth client is per org (an External Client App, no
 * dynamic registration), so the workspace gives its Consumer Key, org environment and MCP server
 * at connect time and `SalesforceConnectController` derives the endpoints and MCP URL. The
 * `refresh_token` scope is needed for a lasting connection.
 */
final class SalesforceConnector extends WorkspaceClientOAuthConnector
{
    #[\Override]
    public string $slug { get => 'salesforce'; }

    #[\Override]
    public string $name { get => 'Salesforce'; }

    #[\Override]
    public string $description { get => 'Read and update accounts, contacts, opportunities, and cases from your Salesforce org.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Sales; }

    #[\Override]
    public string $brandColor { get => '00A1E0'; }

    #[\Override]
    public int $priority { get => 48; }

    #[\Override]
    public string $scopes { get => 'mcp_api refresh_token'; }

    #[\Override]
    public string $helpUrl { get => 'https://developer.salesforce.com/docs/platform/hosted-mcp-servers/guide/create-external-client-app.html'; }
}

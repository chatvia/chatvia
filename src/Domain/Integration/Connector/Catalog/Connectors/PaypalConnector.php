<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * PayPal's hosted MCP server (the path is `/http`); OAuth with dynamic client registration.
 */
final class PaypalConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'paypal'; }

    #[\Override]
    public string $name { get => 'PayPal'; }

    #[\Override]
    public string $description { get => 'Look up invoices, orders, transactions, disputes, and shipment tracking in PayPal.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Payments; }

    #[\Override]
    public string $brandColor { get => '003087'; }

    #[\Override]
    public int $priority { get => 105; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.paypal.com/http'; }
}

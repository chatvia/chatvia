<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Stripe's hosted MCP server; OAuth with dynamic client registration.
 */
final class StripeConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'stripe'; }

    #[\Override]
    public string $name { get => 'Stripe'; }

    #[\Override]
    public string $description { get => 'Search transactions, customers, products, and payments in your Stripe account.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Payments; }

    #[\Override]
    public string $brandColor { get => '635BFF'; }

    #[\Override]
    public int $priority { get => 100; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.stripe.com'; }
}

<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\Concerns\HostsOnChatvia;
use Domain\Integration\Connector\Catalog\TokenConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Currency conversion with ExchangeRate-API, hosted by this app. The workspace's own key is
 * sent as a bearer token and read back by `ExchangeRateTool`.
 */
final class CurrencyConnector extends TokenConnector
{
    use HostsOnChatvia;

    #[\Override]
    public string $slug { get => 'currency'; }

    #[\Override]
    public string $name { get => 'Currency Converter'; }

    #[\Override]
    public string $description { get => 'Convert amounts between any two currencies using current exchange rates (exchangerate-api).'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Utilities; }

    #[\Override]
    public string $brandColor { get => '10B981'; }

    #[\Override]
    public int $priority { get => 20; }

    #[\Override]
    public string $logoUrl { get => '/images/logos/connectors/currency-convertor.svg'; }

    #[\Override]
    public ?string $activityLabel { get => 'Converting currency…'; }

    #[\Override]
    public string $tokenLabel { get => 'ExchangeRate-API key'; }

    #[\Override]
    public string $tokenHelpUrl { get => 'https://app.exchangerate-api.com/dashboard'; }
}

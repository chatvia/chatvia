<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\Concerns\HostsOnChatvia;
use Domain\Integration\Connector\Catalog\TokenConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Live weather from OpenWeatherMap, hosted by this app. The workspace's own OpenWeatherMap
 * key is sent as a bearer token and read back by `CurrentWeatherTool`.
 */
final class WeatherConnector extends TokenConnector
{
    use HostsOnChatvia;

    #[\Override]
    public string $slug { get => 'weather'; }

    #[\Override]
    public string $name { get => 'Weather'; }

    #[\Override]
    public string $description { get => 'Live weather (temperature, conditions, humidity, wind) for any city, powered by OpenWeatherMap.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Utilities; }

    #[\Override]
    public string $brandColor { get => '3B82F6'; }

    #[\Override]
    public int $priority { get => 10; }

    #[\Override]
    public ?string $activityLabel { get => 'Checking the weather…'; }

    #[\Override]
    public string $tokenLabel { get => 'OpenWeatherMap API key'; }

    #[\Override]
    public string $tokenHelpUrl { get => 'https://home.openweathermap.org/api_keys'; }
}

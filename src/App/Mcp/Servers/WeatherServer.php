<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Weather\CurrentWeatherTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The Weather connector (bring-your-own OpenWeatherMap key).
 */
#[Name('Weather')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Live weather lookup powered by OpenWeatherMap. Exposes a single tool:
- current-weather: temperature, conditions, humidity, and wind for a city.
Use this when the user asks about the weather anywhere in the world.
TXT)]
class WeatherServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        CurrentWeatherTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}

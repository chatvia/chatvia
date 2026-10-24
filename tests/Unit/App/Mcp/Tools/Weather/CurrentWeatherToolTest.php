<?php

declare(strict_types=1);

use App\Mcp\McpCallerContext;
use App\Mcp\Servers\WeatherServer;
use App\Mcp\Tools\Weather\CurrentWeatherTool;
use Illuminate\Support\Facades\Http;
use Tests\Support\Mcp\InternalMcp;

/**
 * The current-weather tool wraps OpenWeather with the workspace's own key.
 * These snapshots pin both sides of each call: the exact request sent
 * upstream and the exact MCP result the model receives.
 */
beforeEach(function (): void {
    config(['services.openweather.base_url' => 'https://weather.example.test/data/2.5']);

    Http::fake([
        'weather.example.test/data/2.5/weather?q=Oslo*' => Http::response([
            'name' => 'Oslo',
            'sys' => ['country' => 'NO'],
            'main' => ['temp' => 4.5, 'feels_like' => 1.2, 'humidity' => 81],
            'weather' => [['description' => 'light rain']],
            'wind' => ['speed' => 3.6],
        ]),
        'weather.example.test/data/2.5/weather?q=Nowhere*' => Http::response(['message' => 'city not found'], 404),
        'weather.example.test/data/2.5/weather?q=Broken*' => Http::response([], 500),
        'weather.example.test/data/2.5/weather?q=Calm*' => Http::response([
            'name' => 'Calm',
            'main' => ['temp' => 70, 'feels_like' => 71, 'humidity' => 40],
            'weather' => [],
        ]),
    ]);
});

it('returns a stable weather result', function (array $arguments): void {
    app()->instance(McpCallerContext::class, new McpCallerContext(connectorApiKey: 'weather-key'));

    expect(InternalMcp::resultWithRequestsJson(WeatherServer::tool(CurrentWeatherTool::class, $arguments)))->toMatchSnapshot();
})->with([
    'metric' => [['location' => 'Oslo']],
    'imperial' => [['location' => 'Oslo', 'units' => 'imperial']],
    'no wind, no country, no description' => [['location' => 'Calm', 'units' => 'imperial']],
    'unknown city' => [['location' => 'Nowhere']],
    'upstream failure' => [['location' => 'Broken']],
    'missing location' => [[]],
    'invalid units' => [['location' => 'Oslo', 'units' => 'kelvin']],
]);

it('refuses a weather lookup without an API key', function (): void {
    app()->instance(McpCallerContext::class, new McpCallerContext);

    expect(InternalMcp::resultWithRequestsJson(WeatherServer::tool(CurrentWeatherTool::class, ['location' => 'Oslo'])))->toMatchSnapshot();
});

<?php

namespace App\Mcp\Tools\Weather;

use App\Mcp\McpCallerContext;
use Domain\Integration\Builtin\Enums\WeatherUnit;
use Domain\Integration\Builtin\Exceptions\WeatherLookupException;
use Domain\Integration\Builtin\Services\OpenWeatherClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The Weather connector's only tool: the current weather for a city, looked
 * up with the workspace's own OpenWeatherMap key.
 */
#[Description('Get the current weather for a city using OpenWeatherMap. Returns temperature, conditions, humidity, and wind.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class CurrentWeatherTool extends Tool
{
    public function handle(Request $request, McpCallerContext $caller, OpenWeatherClient $weather): Response|ResponseFactory
    {
        $validated = $request->validate([
            'location' => ['required', 'string', 'max:100'],
            'units' => ['nullable', Rule::enum(WeatherUnit::class)],
        ], [
            'location.required' => 'You must specify a city to get the weather for. For example, "Oslo" or "Tokyo".',
            'units.enum' => 'units must be either "metric" (Celsius) or "imperial" (Fahrenheit).',
        ]);

        $units = WeatherUnit::from($validated['units'] ?? WeatherUnit::Metric->value);

        // The customer supplies their own OpenWeatherMap key when they connect
        // this connector; McpClient forwards it as a Bearer token.
        if ($caller->connectorApiKey === null) {
            return Response::error('No OpenWeatherMap API key was provided. Reconnect the Weather connector with a valid key.');
        }

        try {
            $report = $weather->current($caller->connectorApiKey, $validated['location'], $units);
        } catch (WeatherLookupException $e) {
            return Response::error($e->getMessage());
        }

        $summary = sprintf(
            'Weather in %s%s: %s%s (feels like %s%s), %s, humidity %d%%%s.',
            $report->city,
            $report->country !== '' ? ", {$report->country}" : '',
            $report->temperature,
            $units->temperatureSymbol(),
            $report->feelsLike,
            $units->temperatureSymbol(),
            $report->conditions,
            $report->humidity,
            $report->wind !== null ? ", wind {$report->wind} {$units->windSpeedUnit()}" : '',
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'location' => $report->city,
            'country' => $report->country,
            'temperature' => $report->temperature,
            'feels_like' => $report->feelsLike,
            'units' => $units->value,
            'humidity' => $report->humidity,
            'wind' => $report->wind,
            'conditions' => $report->conditions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()
                ->description('The city to look up, e.g. "Oslo", "Tokyo", or "New York,US".')
                ->required(),

            'units' => $schema->string()
                ->enum(['metric', 'imperial'])
                ->description('Temperature units — "metric" for Celsius (default), "imperial" for Fahrenheit.')
                ->default('metric'),
        ];
    }
}

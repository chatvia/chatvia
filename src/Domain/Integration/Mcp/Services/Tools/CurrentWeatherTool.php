<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the current weather for a city using OpenWeatherMap. Returns temperature, conditions, humidity, and wind.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class CurrentWeatherTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'location' => ['required', 'string', 'max:100'],
            'units' => ['nullable', 'in:metric,imperial'],
        ], [
            'location.required' => 'You must specify a city to get the weather for. For example, "Oslo" or "Tokyo".',
            'units.in' => 'units must be either "metric" (Celsius) or "imperial" (Fahrenheit).',
        ]);

        $units = $validated['units'] ?? 'metric';
        $apiKey = config('services.openweather.key');

        if (! $apiKey) {
            return Response::error('OpenWeatherMap API key is not configured on the server.');
        }

        $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
            'q' => $validated['location'],
            'appid' => $apiKey,
            'units' => $units,
        ]);

        if ($response->status() === 404) {
            return Response::error("Couldn't find a city called \"{$validated['location']}\".");
        }
        if ($response->failed()) {
            return Response::error("Weather API returned HTTP {$response->status()}.");
        }

        $body = $response->json();
        $temp = $body['main']['temp'];
        $feels = $body['main']['feels_like'];
        $humidity = $body['main']['humidity'];
        $description = $body['weather'][0]['description'] ?? 'n/a';
        $wind = $body['wind']['speed'] ?? null;
        $city = $body['name'];
        $country = $body['sys']['country'] ?? '';
        $symbol = $units === 'imperial' ? '°F' : '°C';
        $windUnit = $units === 'imperial' ? 'mph' : 'm/s';

        $summary = sprintf(
            'Weather in %s%s: %s%s (feels like %s%s), %s, humidity %d%%%s.',
            $city,
            $country ? ', '.$country : '',
            $temp,
            $symbol,
            $feels,
            $symbol,
            $description,
            $humidity,
            $wind !== null ? ', wind '.$wind.' '.$windUnit : '',
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'location' => $city,
            'country' => $country,
            'temperature' => $temp,
            'feels_like' => $feels,
            'units' => $units,
            'humidity' => $humidity,
            'wind' => $wind,
            'conditions' => $description,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
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

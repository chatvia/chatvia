<?php

declare(strict_types=1);

use Domain\Integration\Builtin\Enums\WeatherUnit;
use Domain\Integration\Builtin\Exceptions\WeatherLookupException;
use Domain\Integration\Builtin\Services\OpenWeatherClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Pest\Expectation;

/**
 * Current weather from OpenWeather with the workspace's own key: the report
 * read from the reply, and a connection error that never shows the
 * key-bearing URL.
 */
beforeEach(function (): void {
    config(['services.openweather.base_url' => 'https://weather.example.test/data/2.5/']);
});

it('reads the current weather with the caller\'s key and units', function (): void {
    Http::fake(['weather.example.test/*' => Http::response([
        'name' => 'Oslo',
        'sys' => ['country' => 'NO'],
        'main' => ['temp' => 40.1, 'feels_like' => 35, 'humidity' => 81],
        'weather' => [['description' => 'snow']],
        'wind' => ['speed' => 7],
    ])]);

    $report = app(OpenWeatherClient::class)->current('the-key', 'Oslo', WeatherUnit::Imperial);

    expect($report->city)->toBe('Oslo')
        ->and($report->country)->toBe('NO')
        ->and($report->temperature)->toBe(40.1)
        ->and($report->feelsLike)->toBe(35)
        ->and($report->humidity)->toBe(81)
        ->and($report->wind)->toBe(7)
        ->and($report->conditions)->toBe('snow')
        ->and($report->units)->toBe(WeatherUnit::Imperial);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://weather.example.test/data/2.5/weather?q=Oslo&appid=the-key&units=imperial');
});

it('hides connection errors, whose message carries the key-bearing URL', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://weather.example.test/data/2.5/weather?appid=the-key'));

    expect(fn () => app(OpenWeatherClient::class)->current('the-key', 'Oslo', WeatherUnit::Metric))
        ->toThrow(fn (WeatherLookupException $e): Expectation => expect($e->getMessage())
            ->toBe('Could not reach the weather service — please try again.')
            ->not->toContain('the-key'));
});

<?php

use Domain\Integration\Builtin\Enums\WeatherUnits;
use Domain\Integration\Builtin\Exceptions\WeatherLookupException;
use Domain\Integration\Builtin\Services\OpenWeatherClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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

    $report = app(OpenWeatherClient::class)->current('the-key', 'Oslo', WeatherUnits::Imperial);

    expect($report->city)->toBe('Oslo')
        ->and($report->country)->toBe('NO')
        ->and($report->temperature)->toBe(40.1)
        ->and($report->feelsLike)->toBe(35)
        ->and($report->humidity)->toBe(81)
        ->and($report->wind)->toBe(7)
        ->and($report->conditions)->toBe('snow')
        ->and($report->units)->toBe(WeatherUnits::Imperial);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://weather.example.test/data/2.5/weather?q=Oslo&appid=the-key&units=imperial');
});

it('fills the optional fields when the provider leaves them out', function (): void {
    Http::fake(['weather.example.test/*' => Http::response(['name' => 'Nowhere', 'main' => ['temp' => 1, 'feels_like' => 1, 'humidity' => 1]])]);

    $report = app(OpenWeatherClient::class)->current('the-key', 'Nowhere', WeatherUnits::Metric);

    expect($report->country)->toBe('')
        ->and($report->wind)->toBeNull()
        ->and($report->conditions)->toBe('n/a');
});

it('explains a lookup that failed', function (int $status, string $message): void {
    Http::fake(['weather.example.test/*' => Http::response([], $status)]);

    expect(fn () => app(OpenWeatherClient::class)->current('the-key', 'Atlantis', WeatherUnits::Metric))
        ->toThrow(WeatherLookupException::class, $message);
})->with([
    'unknown city' => [404, "Couldn't find a city called \"Atlantis\"."],
    'provider error' => [503, 'Weather API returned HTTP 503.'],
]);

it('hides connection errors, whose message carries the key-bearing URL', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://weather.example.test/data/2.5/weather?appid=the-key'));

    try {
        app(OpenWeatherClient::class)->current('the-key', 'Oslo', WeatherUnits::Metric);
        $this->fail('Expected a lookup failure.');
    } catch (WeatherLookupException $e) {
        expect($e->getMessage())->toBe('Could not reach the weather service — please try again.')
            ->not->toContain('the-key');
    }
});

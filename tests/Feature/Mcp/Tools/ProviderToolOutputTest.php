<?php

use App\Mcp\McpCallerContext;
use App\Mcp\Servers\CurrencyConverterServer;
use App\Mcp\Servers\PerplexityServer;
use App\Mcp\Servers\WeatherServer;
use App\Mcp\Tools\Currency\ExchangeRateTool;
use App\Mcp\Tools\Perplexity\PerplexityAskTool;
use App\Mcp\Tools\Perplexity\PerplexityFetchUrlTool;
use App\Mcp\Tools\Perplexity\PerplexityFinanceSearchTool;
use App\Mcp\Tools\Perplexity\PerplexityPeopleSearchTool;
use App\Mcp\Tools\Perplexity\PerplexityResearchTool;
use App\Mcp\Tools\Perplexity\PerplexityRunCodeTool;
use App\Mcp\Tools\Perplexity\PerplexityWebSearchTool;
use App\Mcp\Tools\Weather\CurrentWeatherTool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Testing\TestResponse;

/**
 * The weather, currency and Perplexity tools wrap third-party APIs with the
 * workspace's own key. These snapshots pin both sides of each call: the exact
 * request we send upstream and the exact MCP result the model receives.
 */

/** Act as a caller whose connector forwards this API key (or none). */
function providerCallerKey(?string $key): void
{
    app()->instance(McpCallerContext::class, new McpCallerContext(connectorApiKey: $key));
}

/** The tool result plus every outbound request, as pretty JSON. */
function providerSnapshot(TestResponse $response): string
{
    $body = (fn (): array => $this->response->toArray())->call($response);

    $requests = Http::recorded()->map(fn (array $pair): array => [
        'method' => $pair[0]->method(),
        'url' => $pair[0]->url(),
        'authorization' => $pair[0]->header('Authorization')[0] ?? null,
        'body' => $pair[0]->data(),
    ])->all();

    return json_encode([
        'requests' => $requests,
        'result' => $body['result'] ?? $body['error'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
    config([
        'services.openweather.base_url' => 'https://weather.example.test/data/2.5',
        'services.exchange_rate.base_url' => 'https://rates.example.test/v6',
        'services.perplexity.base_url' => 'https://perplexity.example.test',
        'services.perplexity.agent_model' => 'perplexity/sonar',
        'services.perplexity.ask_model' => 'sonar-pro',
        'services.perplexity.research_model' => 'sonar-reasoning-pro',
    ]);
});

// --- Weather -------------------------------------------------------------

function fakeWeather(): void
{
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
}

dataset('weather calls', [
    'metric' => [['location' => 'Oslo']],
    'imperial' => [['location' => 'Oslo', 'units' => 'imperial']],
    'no wind, no country, no description' => [['location' => 'Calm', 'units' => 'imperial']],
    'unknown city' => [['location' => 'Nowhere']],
    'upstream failure' => [['location' => 'Broken']],
    'missing location' => [[]],
    'invalid units' => [['location' => 'Oslo', 'units' => 'kelvin']],
]);

it('returns a stable weather result', function (array $arguments): void {
    fakeWeather();
    providerCallerKey('weather-key');

    expect(providerSnapshot(WeatherServer::tool(CurrentWeatherTool::class, $arguments)))->toMatchSnapshot();
})->with('weather calls');

it('refuses a weather lookup without an API key', function (): void {
    fakeWeather();
    providerCallerKey(null);

    expect(providerSnapshot(WeatherServer::tool(CurrentWeatherTool::class, ['location' => 'Oslo'])))->toMatchSnapshot();
});

it('hides weather connection errors that carry the key-bearing URL', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 6 for https://weather.example.test/data/2.5/weather?appid=weather-key'));
    providerCallerKey('weather-key');

    WeatherServer::tool(CurrentWeatherTool::class, ['location' => 'Oslo'])
        ->assertHasErrors(['Could not reach the weather service — please try again.'])
        ->assertDontSee('weather-key');
});

// --- Currency ------------------------------------------------------------

function fakeRates(): void
{
    Http::fake(fn (ClientRequest $request) => match ($request->header('Authorization')[0] ?? null) {
        'Bearer rates-key' => Http::response([
            'result' => 'success',
            'conversion_rates' => ['USD' => 1, 'EUR' => 0.92, 'JPY' => 151.37],
        ]),
        default => Http::response(['result' => 'error'], 403),
    });
}

dataset('currency calls', [
    'usd to eur, default amount' => [['to' => 'eur']],
    'eur to jpy with amount' => [['from' => 'EUR', 'to' => 'JPY', 'amount' => 250.5]],
    'unknown target' => [['to' => 'XXX']],
    'unknown source' => [['from' => 'ABC', 'to' => 'EUR']],
    'bad code length' => [['to' => 'EURO']],
]);

it('returns a stable currency result', function (array $arguments): void {
    fakeRates();
    providerCallerKey('rates-key');

    expect(providerSnapshot(CurrencyConverterServer::tool(ExchangeRateTool::class, $arguments)))->toMatchSnapshot();
})->with('currency calls');

it('reports unavailable rates for a rejected key', function (): void {
    fakeRates();
    providerCallerKey('bad-key');

    expect(providerSnapshot(CurrencyConverterServer::tool(ExchangeRateTool::class, ['to' => 'EUR'])))->toMatchSnapshot();
});

it('refuses a conversion without an API key', function (): void {
    fakeRates();
    providerCallerKey(null);

    expect(providerSnapshot(CurrencyConverterServer::tool(ExchangeRateTool::class, ['to' => 'EUR'])))->toMatchSnapshot();
});

// --- Perplexity ----------------------------------------------------------

function fakePerplexity(): void
{
    Http::fake([
        'perplexity.example.test/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => "<think>internal reasoning</think>\n  The answer is 42.  "]]],
            'search_results' => [
                ['title' => 'Guide', 'url' => 'https://example.com/guide'],
                ['title' => 'Duplicate', 'url' => 'https://example.com/guide'],
                ['title' => '', 'url' => 'https://example.com/untitled'],
                ['title' => 'No URL'],
            ],
        ]),
        'perplexity.example.test/v1/agent' => Http::response([
            'output' => [
                ['type' => 'web_search_results', 'results' => [
                    ['title' => 'Result A', 'url' => 'https://a.example'],
                    ['title' => 'Result B', 'url' => 'https://b.example', 'sources' => ['https://c.example', 'https://a.example', 7]],
                    'not-an-array',
                ]],
                ['type' => 'fetch_url_results', 'contents' => [['title' => 'Page', 'url' => 'https://page.example']]],
                ['type' => 'sandbox_results', 'language' => 'python', 'code' => 'print(6*7)', 'status' => 'completed', 'results' => [
                    ['stdout' => "42\n", 'stderr' => '', 'exit_code' => 0],
                    'skip-me',
                ]],
                'not-an-item',
                ['type' => 'message', 'role' => 'assistant', 'content' => [['text' => 'Agent says '], ['text' => 'hello.'], ['other' => true]]],
            ],
        ]),
    ]);
}

dataset('perplexity calls', [
    'web_search' => [PerplexityWebSearchTool::class, ['query' => 'latest news']],
    'fetch_url with question' => [PerplexityFetchUrlTool::class, ['url' => 'https://example.com/pricing', 'question' => 'What does it cost?']],
    'fetch_url without question' => [PerplexityFetchUrlTool::class, ['url' => 'https://example.com/pricing']],
    'finance_search' => [PerplexityFinanceSearchTool::class, ['query' => 'NVDA share price']],
    'people_search' => [PerplexityPeopleSearchTool::class, ['query' => 'Lisa Su, CEO of AMD']],
    'run_code' => [PerplexityRunCodeTool::class, ['task' => 'compute 6*7']],
    'ask' => [PerplexityAskTool::class, ['query' => 'why is the sky blue']],
    'research' => [PerplexityResearchTool::class, ['query' => 'history of the euro']],
    'web_search, missing query' => [PerplexityWebSearchTool::class, []],
    'fetch_url, invalid url' => [PerplexityFetchUrlTool::class, ['url' => 'not a url']],
    'run_code, missing task' => [PerplexityRunCodeTool::class, []],
    'ask, query too short' => [PerplexityAskTool::class, ['query' => 'x']],
]);

it('returns a stable Perplexity result', function (string $tool, array $arguments): void {
    fakePerplexity();
    providerCallerKey('pplx-key');

    expect(providerSnapshot(PerplexityServer::tool($tool, $arguments)))->toMatchSnapshot();
})->with('perplexity calls');

it('surfaces Perplexity upstream failures', function (): void {
    providerCallerKey('pplx-key');
    Http::fake(['perplexity.example.test/*' => Http::response([], 429)]);

    PerplexityServer::tool(PerplexityAskTool::class, ['query' => 'anything'])->assertHasErrors(['Perplexity returned HTTP 429.']);
    PerplexityServer::tool(PerplexityWebSearchTool::class, ['query' => 'anything'])->assertHasErrors(['Perplexity returned HTTP 429.']);
});

it('surfaces empty Perplexity answers', function (): void {
    providerCallerKey('pplx-key');
    Http::fake(['perplexity.example.test/*' => Http::response([
        'choices' => [['message' => ['content' => '<think>only thoughts</think>']]],
        'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['text' => '  ']]]],
    ])]);

    PerplexityServer::tool(PerplexityResearchTool::class, ['query' => 'anything'])->assertHasErrors(['Perplexity returned no answer.']);
    PerplexityServer::tool(PerplexityPeopleSearchTool::class, ['query' => 'anything'])->assertHasErrors(['Perplexity returned no answer.']);
});

it('keeps an agent answer that has citations but no text', function (): void {
    providerCallerKey('pplx-key');
    Http::fake(['perplexity.example.test/v1/agent' => Http::response([
        'output' => [['type' => 'people_search_results', 'results' => [['title' => 'Profile', 'url' => 'https://profile.example']]]],
    ])]);

    expect(providerSnapshot(PerplexityServer::tool(PerplexityPeopleSearchTool::class, ['query' => 'Jane Doe'])))->toMatchSnapshot();
});

it('falls back to legacy citation URLs on Sonar responses', function (): void {
    providerCallerKey('pplx-key');
    Http::fake(['perplexity.example.test/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => 'Legacy answer.']]],
        'citations' => ['https://one.example', '', 'https://one.example', 'https://two.example'],
    ])]);

    expect(providerSnapshot(PerplexityServer::tool(PerplexityAskTool::class, ['query' => 'legacy please'])))->toMatchSnapshot();
});

it('refuses Perplexity calls without an API key', function (): void {
    fakePerplexity();
    providerCallerKey(null);

    PerplexityServer::tool(PerplexityAskTool::class, ['query' => 'anything'])
        ->assertHasErrors(['No Perplexity API key was provided. Reconnect the Perplexity connector with a valid key.']);
    PerplexityServer::tool(PerplexityRunCodeTool::class, ['task' => 'anything'])
        ->assertHasErrors(['No Perplexity API key was provided. Reconnect the Perplexity connector with a valid key.']);

    Http::assertNothingSent();
});

it('sends the key only as a bearer token', function (): void {
    fakePerplexity();
    providerCallerKey('pplx-key');

    PerplexityServer::tool(PerplexityWebSearchTool::class, ['query' => 'latest news'])->assertOk();

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Authorization', 'Bearer pplx-key')
        && ! Str::contains($request->url(), 'pplx-key'));
});

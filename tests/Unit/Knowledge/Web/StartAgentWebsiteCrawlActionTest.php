<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\StartAgentWebsiteCrawlAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\DomainValidationException;
use Support\Http\SafeUrl;

/**
 * StartAgentWebsiteCrawlAction probes the seed URL from our own servers, so it
 * must not follow redirects, must re-check a redirect target, and must connect
 * to the address it validated (curl host pinning) rather than resolving DNS a
 * second time.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([WebsiteCrawlStarted::class]);
});

afterEach(function (): void {
    SafeUrl::resolveHostsUsing(null);
});

it('creates and attaches the website, then starts the crawl', function (): void {
    Http::fake(['https://docs.example.test/*' => Http::response('<html></html>', 200)]);

    $agent = Agent::factory()->create();

    $website = app(StartAgentWebsiteCrawlAction::class)->execute(
        $agent,
        new CrawlerData(url: 'https://docs.example.test/start'),
    );

    expect($website->url)->toBe('https://docs.example.test/start')
        ->and($website->user_id)->toBe($agent->user_id)
        ->and($agent->websites()->whereKey($website->id)->exists())->toBeTrue();

    Bus::assertDispatched(WebsiteCrawlerJob::class);
    Event::assertDispatched(WebsiteCrawlStarted::class);

    // Redirects are never followed by the HTTP client itself.
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://docs.example.test/start');
});

it('rejects a redirect into a private address outside local and testing', function (): void {
    app()['env'] = 'production';

    Http::fake(['http://93.184.215.14/*' => Http::response('', 301, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);

    $agent = Agent::factory()->create();

    expect(fn () => app(StartAgentWebsiteCrawlAction::class)->execute(
        $agent,
        new CrawlerData(url: 'http://93.184.215.14/start'),
    ))->toThrow(DomainValidationException::class);

    expect(SourceWebsite::query()->where('user_id', $agent->user_id)->exists())->toBeFalse();
    Bus::assertNotDispatched(WebsiteCrawlerJob::class);
});

it('connects to the checked address with redirects off outside local and testing', function (): void {
    app()['env'] = 'production';
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['93.184.215.14']);

    $options = null;
    Http::fake(function (Request $request, array $sent) use (&$options) {
        $options = $sent;

        return Http::response('<html></html>', 200);
    });

    app(StartAgentWebsiteCrawlAction::class)->execute(
        Agent::factory()->create(),
        new CrawlerData(url: 'https://docs.example.test/start'),
    );

    expect($options['allow_redirects'] ?? null)->toBeFalse()
        ->and($options['curl'][CURLOPT_RESOLVE] ?? null)->toBe(['docs.example.test:443:93.184.215.14']);
});

it('refuses a seed URL whose host resolves to a private address', function (): void {
    app()['env'] = 'production';
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['10.0.0.5']);
    Http::fake();

    expect(fn () => app(StartAgentWebsiteCrawlAction::class)->execute(
        Agent::factory()->create(),
        new CrawlerData(url: 'https://intranet.example.test/'),
    ))->toThrow(DomainValidationException::class);

    Http::assertNothingSent();
});

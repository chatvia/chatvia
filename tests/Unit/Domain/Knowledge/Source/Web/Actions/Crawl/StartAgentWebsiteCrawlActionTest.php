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
 * must refuse a seed host that resolves to a private address, must not
 * follow redirects, must re-check a redirect target, and must connect to the
 * address it validated (curl host pinning) rather than resolving DNS a
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
        ->and($website->workspace_id)->toBe($agent->workspace_id)
        ->and($agent->websites()->whereKey($website->id)->exists())->toBeTrue();

    Bus::assertDispatched(WebsiteCrawlerJob::class);
    Event::assertDispatched(WebsiteCrawlStarted::class);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://docs.example.test/start');
});

it('refuses a seed host or a redirect that reaches a private address outside local and testing, and creates nothing', function (): void {
    app()['env'] = 'production';
    $agent = Agent::factory()->create();
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['10.0.0.5']);
    Http::fake(['http://93.184.215.14/*' => Http::response('', 301, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);

    expect(fn () => app(StartAgentWebsiteCrawlAction::class)->execute(
        $agent,
        new CrawlerData(url: 'https://intranet.example.test/'),
    ))->toThrow(DomainValidationException::class);

    Http::assertNothingSent();
    SafeUrl::resolveHostsUsing(null);

    expect(fn () => app(StartAgentWebsiteCrawlAction::class)->execute(
        $agent,
        new CrawlerData(url: 'http://93.184.215.14/start'),
    ))->toThrow(DomainValidationException::class);

    expect(SourceWebsite::query()->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse();
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

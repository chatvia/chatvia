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

it('pins the validated address with curl and keeps redirects off', function (): void {
    $method = new ReflectionMethod(StartAgentWebsiteCrawlAction::class, 'clientOptions');

    $v4 = $method->invoke(app(StartAgentWebsiteCrawlAction::class), 'https://docs.example.test/start', '93.184.215.14');
    $v6 = $method->invoke(app(StartAgentWebsiteCrawlAction::class), 'http://docs.example.test:8080/', '2606:2800:21f:cb07:6820:80da:af6b:8b2c');
    $none = $method->invoke(app(StartAgentWebsiteCrawlAction::class), 'https://docs.example.test/start', null);

    expect($v4['allow_redirects'])->toBeFalse()
        ->and($v4['curl'][CURLOPT_RESOLVE])->toBe(['docs.example.test:443:93.184.215.14'])
        ->and($v6['curl'][CURLOPT_RESOLVE])->toBe(['docs.example.test:8080:[2606:2800:21f:cb07:6820:80da:af6b:8b2c]'])
        ->and($none)->toBe(['allow_redirects' => false]);
});

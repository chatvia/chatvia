<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Discovering a website's pages: one FireCrawl /map call, filtered by the
 * path filters, minus the links the site answers 404 to (FireCrawl remembers
 * a relaunched site's old pages). A re-crawl replaces the URL list in place:
 * gone pages leave with the chunks only they produced, and found pages are
 * trained again.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([WebsiteCrawlStarted::class]);
    config(['services.firecrawl.endpoint' => 'https://firecrawl.test/v2', 'services.firecrawl.key' => 'fc-test']);
});

it('discovers the pages with FireCrawl and keeps the ones the path filters allow', function (): void {
    Http::fake([
        'https://firecrawl.test/v2/map' => Http::response(['links' => [
            'https://docs.example.test/guide/a',
            'https://docs.example.test/guide/a/',
            'https://docs.example.test/blog/b',
        ]]),
        'https://docs.example.test/*' => Http::response('', 200),
    ]);

    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['url' => 'https://docs.example.test', 'status' => SourceStatus::Crawling]);

    app(CrawlWebsiteAction::class)->execute($website, new CrawlerData(url: 'https://docs.example.test/guide', include_paths: 'guide/*'), $agent, 'job-1');

    expect($website->urls()->pluck('url')->all())->toBe(['https://docs.example.test/guide/a'])
        ->and($website->fresh()->status)->toBe(SourceStatus::Crawled)
        ->and($website->fresh()->internal_ingestion_job_id)->toBe('job-1');

    Event::assertDispatched(WebsiteCrawlStarted::class);
});

it('re-crawls in place: gone pages leave with the chunks only they used, found pages train again', function (): void {
    Http::fake([
        'https://firecrawl.test/v2/map' => Http::response(['links' => [
            'https://docs.example.test/kept',
            'https://docs.example.test/new-a',
            'https://docs.example.test/new-b',
        ]]),
        'https://docs.example.test/*' => Http::response('', 200),
    ]);

    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['url' => 'https://docs.example.test', 'status' => SourceStatus::Crawling]);
    $kept = $website->urls()->create(['url' => 'https://docs.example.test/kept', 'status' => 'ingested', 'checksum' => 'kept-checksum']);
    $gone = $website->urls()->create(['url' => 'https://docs.example.test/gone', 'status' => 'ingested']);

    $onlyGone = Chunk::factory()->forSource($website)->referencedBy($gone)->create(['chunk' => 'Only on the gone page.']);
    $shared = Chunk::factory()->forSource($website)->referencedBy($gone, $kept)->create(['chunk' => 'On both pages.']);
    $summary = Chunk::factory()->forSource($website)->summary()->create(['chunk' => 'Summary of the website.']);

    app(CrawlWebsiteAction::class)->execute($website, new CrawlerData(url: 'https://docs.example.test'), $agent);

    $urls = $website->urls()->orderBy('url')->get();

    expect($urls->pluck('url')->all())->toBe([
        'https://docs.example.test/kept',
        'https://docs.example.test/new-a',
        'https://docs.example.test/new-b',
    ])
        ->and($urls->every(fn (AgentWebsiteUrl $url): bool => $url->status === SourceStatus::Pending && $url->created_at !== null))->toBeTrue()
        ->and($kept->fresh()->checksum)->toBe('kept-checksum')
        ->and($gone->fresh())->toBeNull()
        ->and(Chunk::query()->find($onlyGone->id))->toBeNull()
        ->and(Chunk::query()->find($shared->id))->not->toBeNull()
        ->and(Chunk::query()->find($summary->id))->not->toBeNull()
        ->and(ChunkReference::query()->where('referenceable_id', $gone->id)->exists())->toBeFalse()
        ->and($kept->chunks()->pluck('chunks.id')->all())->toBe([$shared->id]);
});

it('drops the discovered links the site no longer serves and keeps the ones it cannot be sure about', function (): void {
    Http::fake([
        'https://firecrawl.test/v2/map' => Http::response(['links' => [
            'https://docs.example.test/live',
            'https://docs.example.test/gone',
            'https://docs.example.test/moved',
            'https://docs.example.test/blocked',
            'https://docs.example.test/down',
        ]]),
        'https://docs.example.test/gone' => Http::response('', 404),
        'https://docs.example.test/moved' => Http::response('', 301, ['Location' => 'https://docs.example.test/live']),
        'https://docs.example.test/blocked' => Http::response('', 403),
        'https://docs.example.test/down' => fn () => throw new ConnectionException('timed out'),
        'https://docs.example.test/*' => Http::response('', 200),
    ]);

    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['url' => 'https://docs.example.test', 'status' => SourceStatus::Crawling]);

    app(CrawlWebsiteAction::class)->execute($website, new CrawlerData(url: 'https://docs.example.test'), $agent);

    expect($website->urls()->orderBy('id')->pluck('url')->all())->toBe([
        'https://docs.example.test/live',
        'https://docs.example.test/moved',
        'https://docs.example.test/blocked',
        'https://docs.example.test/down',
    ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'HEAD' && $request->url() === 'https://docs.example.test/gone');
});

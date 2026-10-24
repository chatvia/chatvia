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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Discovering a website's pages: one FireCrawl /map call, filtered by the
 * path filters. A re-crawl replaces the URL list in place, in one
 * transaction: gone pages leave with the chunks only they produced, found
 * pages are trained again, and a failure keeps the previous list.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([WebsiteCrawlStarted::class]);
    config(['services.firecrawl.endpoint' => 'https://firecrawl.test/v2', 'services.firecrawl.key' => 'fc-test']);
});

it('discovers the pages with FireCrawl and keeps the ones the path filters allow', function (): void {
    Http::fake(['https://firecrawl.test/v2/map' => Http::response(['links' => [
        'https://docs.example.test/guide/a',
        'https://docs.example.test/guide/a/',
        'https://docs.example.test/blog/b',
    ]])]);

    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['url' => 'https://docs.example.test', 'status' => SourceStatus::Crawling]);

    app(CrawlWebsiteAction::class)->execute($website, new CrawlerData(url: 'https://docs.example.test/guide', include_paths: 'guide/*'), $agent, 'job-1');

    expect($website->urls()->pluck('url')->all())->toBe(['https://docs.example.test/guide/a'])
        ->and($website->fresh()->status)->toBe(SourceStatus::Crawled)
        ->and($website->fresh()->internal_ingestion_job_id)->toBe('job-1');

    Event::assertDispatched(WebsiteCrawlStarted::class);
});

it('re-crawls in place: gone pages leave with the chunks only they used, found pages train again', function (): void {
    Http::fake(['https://firecrawl.test/v2/map' => Http::response(['links' => [
        'https://docs.example.test/kept',
        'https://docs.example.test/new-a',
        'https://docs.example.test/new-b',
    ]])]);

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

it('keeps the previous URL list when saving the new one fails', function (): void {
    Http::fake(['https://firecrawl.test/v2/map' => Http::response(['links' => ['https://docs.example.test/new']])]);

    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['url' => 'https://docs.example.test', 'status' => SourceStatus::Crawling]);
    $website->urls()->create(['url' => 'https://docs.example.test/old']);

    // Fails after the new URL rows are written, inside the transaction.
    SourceWebsite::updating(function (): void {
        throw new RuntimeException('database unavailable');
    });

    expect(fn () => app(CrawlWebsiteAction::class)->execute(
        $website,
        new CrawlerData(url: $website->url),
        $agent,
    ))->toThrow(RuntimeException::class);

    expect($website->urls()->pluck('url')->all())->toBe(['https://docs.example.test/old'])
        ->and($website->fresh()->status)->toBe(SourceStatus::Crawling);

    Event::assertNotDispatched(WebsiteCrawlStarted::class);
});

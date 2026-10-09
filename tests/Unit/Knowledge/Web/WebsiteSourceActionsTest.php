<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Actions\Website\CompleteWebsiteIngestionAction;
use Domain\Knowledge\Ingestion\Actions\Website\StartWebsiteIngestionAction;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Knowledge\Source\Web\Actions\Source\RecrawlSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Actions\Source\RetrainSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Actions\Url\DeleteAgentWebsiteUrlsAction;
use Domain\Knowledge\Source\Web\Actions\Url\RetrainWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The website lifecycle: discover (or rediscover) the pages, train them in a
 * batch, finish the batch, retrain one page, and remove pages together with
 * the chunks only they produced.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([WebsiteCrawlStarted::class, WebsiteIngestionProgressed::class]);
});

function wsaWebsite(Agent $agent, string $status = 'ingested', array $options = [], string $url = 'https://docs.example.test'): SourceWebsite
{
    $website = SourceWebsite::query()->create([
        'user_id' => $agent->user_id,
        'url' => $url,
        'status' => $status,
        'options' => $options,
    ]);
    $agent->websites()->attach($website->id);

    return $website;
}

function wsaChunk(SourceWebsite $website, string $text, array $urls): Chunk
{
    $chunk = Chunk::query()->create([
        'user_id' => $website->user_id,
        'chunkable_type' => $website->getMorphClass(),
        'chunkable_id' => $website->id,
        'chunk_index' => 1,
        'chunk' => $text,
        'tokens' => 3,
        'embedding' => app(EmbeddingsProviderInterface::class)->embed($text, true),
        'embedding_bm25' => Bm25Tokenizer::expression($text),
        'checksum' => hash('sha256', $text),
        'cost_in_micro_cents' => 0,
    ]);

    foreach ($urls as $url) {
        ChunkReference::query()->insert(['chunk_id' => $chunk->id, 'referenceable_type' => $url->getMorphClass(), 'referenceable_id' => $url->id]);
    }

    return $chunk;
}

it('discovers the pages with FireCrawl and keeps the ones the path filters allow', function (): void {
    config(['services.firecrawl.endpoint' => 'https://firecrawl.test/v2', 'services.firecrawl.key' => 'fc-test']);
    Http::fake(['https://firecrawl.test/v2/map' => Http::response(['links' => [
        'https://docs.example.test/guide/a',
        'https://docs.example.test/guide/a/',
        'https://docs.example.test/blog/b',
    ]])]);

    $agent = Agent::factory()->create();
    $website = wsaWebsite($agent, 'crawling');

    app(CrawlWebsiteAction::class)->execute($website, new CrawlerData(url: 'https://docs.example.test/guide', include_paths: 'guide/*'), $agent, 'job-1');

    expect($website->urls()->pluck('url')->all())->toBe(['https://docs.example.test/guide/a'])
        ->and($website->fresh()->status)->toBe(SourceStatus::Crawled)
        ->and($website->fresh()->internal_ingestion_job_id)->toBe('job-1');

    Event::assertDispatched(WebsiteCrawlStarted::class);
});

it('rediscovers a website with the path filters it was added with', function (): void {
    $agent = Agent::factory()->create();
    $website = wsaWebsite($agent, options: ['include_paths' => 'docs/*']);

    app(RecrawlSourceWebsiteAction::class)->execute($website, $agent);

    expect($website->fresh()->status)->toBe(SourceStatus::Crawling);
    Bus::assertDispatched(WebsiteCrawlerJob::class, fn (WebsiteCrawlerJob $job): bool => $job->data->include_paths === 'docs/*');
    Event::assertDispatched(WebsiteCrawlStarted::class);
});

it('retrains a website from the URLs it has', function (): void {
    $agent = Agent::factory()->create();
    $website = wsaWebsite($agent);

    app(RetrainSourceWebsiteAction::class)->execute($website, $agent);

    Bus::assertDispatched(WebsiteIngestionJob::class);
    Bus::assertNotDispatched(WebsiteCrawlerJob::class);
});

it('trains the pending URLs in one batch and records the batch', function (): void {
    $agent = Agent::factory()->create();
    $website = wsaWebsite($agent, 'crawled');
    $website->urls()->createMany([['url' => 'https://docs.example.test/a'], ['url' => 'https://docs.example.test/b']]);

    app(StartWebsiteIngestionAction::class)->execute($website, $agent, 'job-2');

    $website->refresh();

    expect($website->status)->toBe(SourceStatus::Processing)
        ->and($website->expected_chunks)->toBe(2)
        ->and($website->internal_ingestion_job_id)->toBe('job-2')
        ->and($website->internal_chunking_batch_id)->not->toBeNull();

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2 && $batch->jobs->first() instanceof WebsiteUrlTrainJob);
});

it('finishes a website: ingested, text file saved, URL placeholders taken off the chunk count', function (): void {
    Storage::fake();
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $website = wsaWebsite($agent, 'ingesting');
    $url = $website->urls()->create(['url' => 'https://docs.example.test/a']);
    wsaChunk($website, 'First part of the page.', [$url]);
    $website->update(['expected_chunks' => 1 + 1]); // one URL placeholder + one real chunk

    app(CompleteWebsiteIngestionAction::class)->execute($website, $agent);

    $website->refresh();

    expect($website->status)->toBe(SourceStatus::Completed)
        ->and($website->expected_chunks)->toBe(1)
        ->and(Storage::get($website->filepath))->toBe('First part of the page.');

    Bus::assertDispatched(SummaryIngestionJob::class);
    Event::assertDispatched(WebsiteIngestionProgressed::class);
});

it('retrains one URL', function (): void {
    $agent = Agent::factory()->create();
    $website = wsaWebsite($agent);
    $url = $website->urls()->create(['url' => 'https://docs.example.test/a', 'status' => 'ingested']);

    app(RetrainWebsiteUrlAction::class)->execute($url, $agent);

    expect($url->fresh()->status)->toBe(SourceStatus::Pending);
    Bus::assertDispatched(WebsiteUrlTrainJob::class);
});

it('removes several URLs with the chunks only they produced', function (): void {
    $agent = Agent::factory()->create();
    $website = wsaWebsite($agent);
    $removedA = $website->urls()->create(['url' => 'https://docs.example.test/a']);
    $removedB = $website->urls()->create(['url' => 'https://docs.example.test/b']);
    $kept = $website->urls()->create(['url' => 'https://docs.example.test/c']);

    $onlyA = wsaChunk($website, 'Only on page A.', [$removedA]);
    $shared = wsaChunk($website, 'On pages B and C.', [$removedB, $kept]);

    $foreign = wsaWebsite($agent, url: 'https://other.example.test');
    $foreignUrl = $foreign->urls()->create(['url' => 'https://docs.example.test/x']);

    $count = app(DeleteAgentWebsiteUrlsAction::class)->execute($website, [$removedA->id, $removedB->id, $foreignUrl->id]);

    expect($count)->toBe(2)
        ->and($website->urls()->pluck('url')->all())->toBe(['https://docs.example.test/c'])
        ->and(Chunk::query()->find($onlyA->id))->toBeNull()
        ->and(Chunk::query()->find($shared->id))->not->toBeNull()
        ->and($foreignUrl->fresh())->not->toBeNull();
});

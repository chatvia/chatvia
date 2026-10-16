<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Knowledge\Source\Web\Actions\Url\DeleteAgentWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Saving a website's new URL list runs in one transaction, and deleting a URL
 * keeps the chunks that another URL of the site still uses.
 */
function writeActionsWebsite(Agent $agent): SourceWebsite
{
    $website = SourceWebsite::create([
        'workspace_id' => $agent->workspace_id,
        'url' => 'https://docs.example.test',
        'status' => 'crawling',
    ]);
    $agent->websites()->attach($website->id);

    return $website;
}

it('deletes only the chunks no other URL still uses', function (): void {
    $agent = Agent::factory()->create();
    $website = writeActionsWebsite($agent);

    $deleted = $website->urls()->create(['url' => 'https://docs.example.test/a']);
    $kept = $website->urls()->create(['url' => 'https://docs.example.test/b']);

    $own = Chunk::factory()->forSource($website)->create(['chunk' => 'Only on page A']);
    $shared = Chunk::factory()->forSource($website)->create(['chunk' => 'Footer shared by A and B']);

    foreach ([[$own, $deleted], [$shared, $deleted], [$shared, $kept]] as [$chunk, $url]) {
        ChunkReference::query()->insert([
            'chunk_id' => $chunk->id,
            'referenceable_type' => $url->getMorphClass(),
            'referenceable_id' => $url->id,
        ]);
    }

    app(DeleteAgentWebsiteUrlAction::class)->execute($deleted);

    expect(AgentWebsiteUrl::query()->find($deleted->id))->toBeNull()
        ->and(Chunk::query()->find($own->id))->toBeNull()
        ->and(Chunk::query()->find($shared->id))->not->toBeNull()
        ->and($kept->chunks()->pluck('chunks.id')->all())->toBe([$shared->id])
        ->and(ChunkReference::query()->where('referenceable_id', $deleted->id)->exists())->toBeFalse();
});

it('keeps the previous URL list when saving the new one fails', function (): void {
    Event::fake([WebsiteCrawlStarted::class]);

    $agent = Agent::factory()->create();
    $website = writeActionsWebsite($agent);
    $website->urls()->create(['url' => 'https://docs.example.test/old']);

    // Fails right after the new rows are written, inside the transaction.
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with($query->sql, 'insert into "source_website_urls"')) {
            throw new RuntimeException('database unavailable');
        }
    });

    config(['services.firecrawl.endpoint' => 'https://firecrawl.test/v2', 'services.firecrawl.key' => 'fc-test']);
    Http::fake(['https://firecrawl.test/v2/map' => Http::response(['links' => ['https://docs.example.test/new']])]);

    expect(fn () => app(CrawlWebsiteAction::class)->execute(
        $website,
        new CrawlerData(url: $website->url),
        $agent,
    ))->toThrow(RuntimeException::class);

    expect($website->urls()->pluck('url')->all())->toBe(['https://docs.example.test/old'])
        ->and($website->fresh()->status)->toBe(SourceStatus::Crawling);

    Event::assertNotDispatched(WebsiteCrawlStarted::class);
});

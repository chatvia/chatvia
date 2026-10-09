<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Knowledge\Source\Web\Actions\Url\DeleteAgentWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Actions\AddAgentYoutubeVideoAction;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Multi-step writes on website and YouTube sources run in one transaction,
 * and deleting a URL keeps chunks that another URL of the site still uses.
 */
function writeActionsWebsite(Agent $agent): SourceWebsite
{
    $website = SourceWebsite::create([
        'user_id' => $agent->user_id,
        'url' => 'https://docs.example.test',
        'status' => 'crawling',
    ]);
    $agent->websites()->attach($website->id);

    return $website;
}

function writeActionsChunk(SourceWebsite $website, string $text): Chunk
{
    return Chunk::query()->create([
        'user_id' => $website->user_id,
        'chunkable_type' => $website->getMorphClass(),
        'chunkable_id' => $website->id,
        'chunk_index' => 1,
        'chunk' => $text,
        'checksum' => hash('sha256', $text.uniqid()),
        'tokens' => 3,
        'cost_in_micro_cents' => 0,
        'embedding' => '['.implode(',', array_fill(0, 1024, 0.1)).']',
        'embedding_bm25' => Bm25Tokenizer::expression($text),
        'metadata' => [],
    ]);
}

it('deletes only the chunks no other URL still uses', function (): void {
    $agent = Agent::factory()->create();
    $website = writeActionsWebsite($agent);

    $deleted = $website->urls()->create(['url' => 'https://docs.example.test/a']);
    $kept = $website->urls()->create(['url' => 'https://docs.example.test/b']);

    $own = writeActionsChunk($website, 'Only on page A');
    $shared = writeActionsChunk($website, 'Footer shared by A and B');

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

    AgentWebsiteUrl::creating(function (): void {
        throw new RuntimeException('database unavailable');
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

it('rolls back the YouTube video row when attaching it fails', function (): void {
    $agent = Agent::factory()->create();

    SourceYoutubeVideo::created(function (): void {
        throw new RuntimeException('database unavailable');
    });

    expect(fn () => app(AddAgentYoutubeVideoAction::class)->execute(
        $agent,
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ))->toThrow(RuntimeException::class);

    expect(SourceYoutubeVideo::query()->where('user_id', $agent->user_id)->exists())->toBeFalse();
});

it('adds and attaches a YouTube video', function (): void {
    $agent = Agent::factory()->create();

    $video = app(AddAgentYoutubeVideoAction::class)->execute($agent, 'https://youtu.be/dQw4w9WgXcQ');

    expect($agent->youtubeVideos()->whereKey($video->id)->exists())->toBeTrue()
        ->and($video->user_id)->toBe($agent->user_id);
});

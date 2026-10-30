<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Website\TrainWebsiteUrlAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\Knowledge\Ingestion\FakeTextSplitter;

/**
 * TrainWebsiteUrlAction scrapes one crawled page, splits it as Markdown and
 * links each chunk to its URL in chunk_references. The link must use the
 * morph alias (the app reads that column through the enforced morph map), a
 * retrain must not duplicate chunks or links, and a changed page leaves no
 * chunk behind that no page uses.
 */
function twuSplitsInto(string ...$parts): FakeTextSplitter
{
    $splitter = FakeTextSplitter::returning(...$parts);

    app()->instance(TextSplitter::class, $splitter);

    return $splitter;
}

/**
 * A page of a website of a new agent; FireCrawl answers its scrape.
 *
 * @param  array<string, mixed>|null  $page  what FireCrawl returns as `data`, else a page with content
 * @return array{Agent, SourceWebsite, AgentWebsiteUrl}
 */
function twuPage(?array $page = null): array
{
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->processing()->attachedTo($agent)->create(['url' => 'https://docs.example.test']);
    $url = $website->urls()->create(['url' => 'https://docs.example.test/page']);

    config()->set('services.firecrawl.endpoint', 'https://firecrawl.test/v2');
    config()->set('services.firecrawl.key', 'fc-test');
    Http::fake(['https://firecrawl.test/v2/scrape' => Http::response([
        'success' => true,
        'data' => $page ?? ['markdown' => '# Page'.PHP_EOL.'Some content about the product.', 'metadata' => []],
    ], 200)]);

    return [$agent, $website, $url];
}

beforeEach(function (): void {
    Bus::fake();
    Event::fake([UrlIngestionStarted::class, WebsiteIngestionProgressed::class]);
});

it('links the chunks to the URL by the morph alias, once, even on a retrain', function (): void {
    $splitter = twuSplitsInto('First chunk of the page.', 'Second chunk of the page.');
    [$agent, $website, $url] = twuPage();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);
    $url->update(['checksum' => null]);
    app(TrainWebsiteUrlAction::class)->execute($url->fresh(), $agent);

    $firstChunkId = Chunk::query()->where('chunkable_id', $website->id)->value('id');

    expect(DB::table('chunk_references')->where('referenceable_id', $url->id)->pluck('referenceable_type')->unique()->all())->toBe(['agent_website_url'])
        ->and($splitter->kinds)->toBe([ContentKind::Markdown])
        ->and(Chunk::query()->where('chunkable_id', $website->id)->count())->toBe(2)
        ->and(DB::table('chunk_references')->where('referenceable_id', $url->id)->count())->toBe(2)
        ->and(DB::table('chunk_references')->where('chunk_id', $firstChunkId)->pluck('referenceable_id')->all())->toBe([$url->id]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://firecrawl.test/v2/scrape'
        && $request['url'] === $url->url);
});

it('drops the parts a changed page no longer has and deletes the chunks no page uses', function (): void {
    [$agent, $website, $url] = twuPage();
    twuSplitsInto('Old part.', 'Shared part.');
    app(TrainWebsiteUrlAction::class)->execute($url, $agent);
    $other = $website->urls()->create(['url' => 'https://docs.example.test/other']);
    $shared = Chunk::query()->ofSource($website)->where('checksum', hash('sha256', 'Shared part.'))->sole();
    ChunkReference::query()->insert(['chunk_id' => $shared->id, 'referenceable_type' => $other->getMorphClass(), 'referenceable_id' => $other->id]);
    $old = Chunk::query()->ofSource($website)->where('checksum', hash('sha256', 'Old part.'))->sole();
    twuSplitsInto('Shared part.', 'New part.');
    $url->update(['checksum' => null]);
    Cache::flush();

    app(TrainWebsiteUrlAction::class)->execute($url->fresh(), $agent);

    expect(Chunk::query()->find($old->id))->toBeNull()
        ->and(Chunk::query()->find($shared->id))->not->toBeNull()
        ->and($url->chunks()->pluck('chunk')->sort()->values()->all())->toBe(['New part.', 'Shared part.'])
        ->and($other->chunks()->pluck('chunk')->all())->toBe(['Shared part.']);
});

it('stores nothing from a page the site answers with an error', function (): void {
    $splitter = twuSplitsInto('404 Not Found');
    [$agent, $website, $url] = twuPage(['markdown' => '# 404'.PHP_EOL.'Not Found', 'metadata' => ['statusCode' => 404]]);

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    expect(Chunk::query()->where('chunkable_id', $website->id)->count())->toBe(0)
        ->and($splitter->kinds)->toBe([])
        ->and($url->fresh()->status)->toBe(SourceStatus::Completed)
        ->and($url->fresh()->crawled_at)->not->toBeNull();
});

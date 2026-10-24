<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Website\TrainWebsiteUrlAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
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
 * TrainWebsiteUrlAction turns one crawled page into chunks and links each
 * chunk to its URL in chunk_references. The link must use the morph alias
 * (the app reads that column through the enforced morph map), a retrain must
 * not re-link or re-fetch chunks one by one, and a changed page leaves no
 * chunk behind that no page uses.
 */
/**
 * Splits every page into these parts. A web page is scraped as Markdown, so
 * it is split as Markdown ({@see FakeTextSplitter::$kinds}).
 */
function twuSplitsInto(string ...$parts): FakeTextSplitter
{
    $splitter = FakeTextSplitter::returning(...$parts);

    app()->instance(TextSplitter::class, $splitter);

    return $splitter;
}

/**
 * A page of a website of a new agent; FireCrawl answers its scrape with this
 * metadata.
 *
 * @return array{Agent, SourceWebsite, AgentWebsiteUrl}
 */
function twuPage(array $metadata = []): array
{
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->processing()->attachedTo($agent)->create(['url' => 'https://docs.example.test']);
    $url = $website->urls()->create(['url' => 'https://docs.example.test/page']);

    config()->set('services.firecrawl.endpoint', 'https://firecrawl.test/v2');
    config()->set('services.firecrawl.key', 'fc-test');
    Http::fake(['https://firecrawl.test/v2/scrape' => Http::response([
        'success' => true,
        'data' => ['markdown' => '# Page'.PHP_EOL.'Some content about the product.', 'metadata' => $metadata],
    ], 200)]);

    return [$agent, $website, $url];
}

beforeEach(function (): void {
    Bus::fake();
    Event::fake([UrlIngestionStarted::class, WebsiteIngestionProgressed::class]);
});

it('links the chunks to the URL using the morph alias', function (): void {
    $splitter = twuSplitsInto('First chunk of the page.', 'Second chunk of the page.');
    [$agent, $website, $url] = twuPage();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    $types = DB::table('chunk_references')->where('referenceable_id', $url->id)->pluck('referenceable_type')->unique()->all();

    // Discovery stores no page content, so training scrapes the page itself.
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://firecrawl.test/v2/scrape'
        && $request['url'] === $url->url);

    $firstChunkId = Chunk::query()->where('chunkable_id', $website->id)->value('id');

    expect($types)->toBe(['agent_website_url'])
        ->and($splitter->kinds)->toBe([ContentKind::Markdown])
        ->and($url->chunks()->count())->toBe(2)
        ->and(DB::table('chunk_references')->where('chunk_id', $firstChunkId)->pluck('referenceable_id')->all())
        ->toBe([$url->id]);
});

it('reuses existing chunks and links on a retrain without duplicating them', function (): void {
    twuSplitsInto('First chunk of the page.', 'Second chunk of the page.');
    [$agent, $website, $url] = twuPage();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    // Force a retrain of the same content.
    $url->update(['checksum' => null]);

    app(TrainWebsiteUrlAction::class)->execute($url->fresh(), $agent);

    expect(Chunk::query()->where('chunkable_id', $website->id)->count())->toBe(2)
        ->and(DB::table('chunk_references')->where('referenceable_id', $url->id)->count())->toBe(2);
});

it('enhances the new parts before storing them when enhancement is on', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);
    twuSplitsInto('First chunk of the page.', 'Second chunk of the page.');
    [$agent, $website, $url] = twuPage();
    // The scrape keeps the page's stub; every other call is the model's.
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'summary' => 'About the product.',
        'questions' => ['What does the product do?'],
    ])]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]])]);

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    $chunks = Chunk::query()->ofSource($website)->get();

    expect($chunks)->toHaveCount(2)
        ->and($chunks->every(fn (Chunk $chunk): bool => $chunk->is_enhanced && $chunk->summary === 'About the product.'))->toBeTrue();
});

it('keeps the index a shared part got when it was first stored', function (): void {
    [$agent, $website, $url] = twuPage();
    twuSplitsInto('Intro of the first page.', 'Shared footer.');
    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    $other = $website->urls()->create(['url' => 'https://docs.example.test/other']);
    twuSplitsInto('Shared footer.');

    app(TrainWebsiteUrlAction::class)->execute($other, $agent);

    $footer = Chunk::query()->ofSource($website)->where('checksum', hash('sha256', 'Shared footer.'))->sole();

    expect($footer->chunk_index)->toBe(2)
        ->and(DB::table('chunk_references')->where('chunk_id', $footer->id)->count())->toBe(2);
});

it('drops the parts a changed page no longer has and deletes the chunks no page uses', function (): void {
    [$agent, $website, $url] = twuPage();
    twuSplitsInto('Old part.', 'Shared part.');
    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    // Another page shares one part, so that chunk must survive.
    $other = $website->urls()->create(['url' => 'https://docs.example.test/other']);
    $shared = Chunk::query()->ofSource($website)->where('checksum', hash('sha256', 'Shared part.'))->sole();
    ChunkReference::query()->insert(['chunk_id' => $shared->id, 'referenceable_type' => $other->getMorphClass(), 'referenceable_id' => $other->id]);
    $old = Chunk::query()->ofSource($website)->where('checksum', hash('sha256', 'Old part.'))->sole();

    // The page changed: it splits into other parts now.
    twuSplitsInto('Shared part.', 'New part.');
    $url->update(['checksum' => null]);
    Cache::flush();

    app(TrainWebsiteUrlAction::class)->execute($url->fresh(), $agent);

    expect(Chunk::query()->find($old->id))->toBeNull()
        ->and(Chunk::query()->find($shared->id))->not->toBeNull()
        ->and($url->chunks()->pluck('chunk')->sort()->values()->all())->toBe(['New part.', 'Shared part.'])
        ->and($other->chunks()->pluck('chunk')->all())->toBe(['Shared part.']);
});

it('reports the website progress it is given', function (): void {
    twuSplitsInto('Only chunk.');
    [$agent, , $url] = twuPage();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent, 50);

    Event::assertDispatched(WebsiteIngestionProgressed::class, fn (WebsiteIngestionProgressed $event): bool => $event->broadcastWith()['progress'] === 50);
});

it('trains an unchanged page again once the chunk settings change', function (array $change): void {
    twuSplitsInto('First chunk of the page.');
    [$agent, $website, $url] = twuPage();
    config(['chatvia.ai.chunking' => ['unit' => 'tokens', 'size' => 400, 'overlap' => 0, 'strategies' => ['markdown' => 'markdown']]]);

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);
    $first = $url->fresh()->checksum;

    // Same page, same settings: skipped, the checksum stays.
    app(TrainWebsiteUrlAction::class)->execute($url->fresh(), $agent);
    expect($url->fresh()->checksum)->toBe($first);

    config($change);
    app(TrainWebsiteUrlAction::class)->execute($url->fresh(), $agent);

    expect($url->fresh()->checksum)->not->toBe($first);
})->with([
    'the size' => [['chatvia.ai.chunking.size' => 800]],
    'the unit' => [['chatvia.ai.chunking.unit' => 'characters']],
    'the strategy' => [['chatvia.ai.chunking.strategies.markdown' => 'recursive']],
]);

it('keeps the page title FireCrawl found, to name the page as a source', function (array $metadata, ?string $title): void {
    twuSplitsInto('First chunk of the page.');
    [$agent, $website, $url] = twuPage($metadata);

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    expect($url->fresh()->title)->toBe($title);
})->with([
    'the page title' => [['title' => ' Returns and refunds ', 'ogTitle' => 'OG title'], 'Returns and refunds'],
    'the Open Graph title' => [['ogTitle' => 'OG title'], 'OG title'],
    'none' => [[], null],
]);

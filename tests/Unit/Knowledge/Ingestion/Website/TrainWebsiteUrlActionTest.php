<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Actions\Website\TrainWebsiteUrlAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * TrainWebsiteUrlAction turns one crawled page into chunks and links each
 * chunk to its URL in chunk_references. The link must use the morph alias
 * (the app reads that column through the enforced morph map), a retrain must
 * not re-link or re-fetch chunks one by one, and a changed page leaves no
 * chunk behind that no page uses.
 */
function trainUrlFakes(array $chunks): void
{
    // Web pages are scraped as Markdown, so they are split as Markdown.
    $splitter = test()->mock(TextSplitter::class);
    $splitter->shouldReceive('split')->with(Mockery::type('string'), ContentKind::Markdown)->andReturn($chunks);
    $splitter->shouldReceive('name')->andReturn('builtin');

    app()->bind(EmbeddingsProviderInterface::class, fn (): EmbeddingsProviderInterface => new class implements EmbeddingsProviderInterface
    {
        public function embed(string $text, bool $returnAsText = false): string|array
        {
            $vector = array_fill(0, 1024, 0.1);

            return $returnAsText ? '['.implode(',', $vector).']' : $vector;
        }

        public function embedQuery(string $text, bool $returnAsText = false): string|array
        {
            return $this->embed($text, $returnAsText);
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn (string $text): array => (array) $this->embed($text), $texts);
        }

        public function getDimensions(): int
        {
            return 1024;
        }

        public function getModelName(): string
        {
            return 'fake-embed';
        }
    });
}

function trainUrlFixture(array $metadata = []): array
{
    $agent = Agent::factory()->create();

    $website = SourceWebsite::create([
        'workspace_id' => $agent->workspace_id,
        'url' => 'https://docs.example.test',
        'status' => 'ingesting',
    ]);
    $agent->websites()->attach($website->id);

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
    trainUrlFakes(['First chunk of the page.', 'Second chunk of the page.']);
    [$agent, $website, $url] = trainUrlFixture();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    $types = DB::table('chunk_references')->where('referenceable_id', $url->id)->pluck('referenceable_type')->unique()->all();

    // Discovery stores no page content, so training scrapes the page itself.
    Http::assertSent(fn ($request): bool => $request->url() === 'https://firecrawl.test/v2/scrape'
        && $request['url'] === $url->url);

    $firstChunkId = Chunk::query()->where('chunkable_id', $website->id)->value('id');

    expect($types)->toBe(['agent_website_url'])
        ->and($url->chunks()->count())->toBe(2)
        ->and(DB::table('chunk_references')->where('chunk_id', $firstChunkId)->pluck('referenceable_id')->all())
        ->toBe([$url->id]);
});

it('reuses existing chunks and links on a retrain without duplicating them', function (): void {
    trainUrlFakes(['First chunk of the page.', 'Second chunk of the page.']);
    [$agent, $website, $url] = trainUrlFixture();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    // Force a retrain of the same content.
    $url->update(['checksum' => null]);

    app(TrainWebsiteUrlAction::class)->execute($url->fresh(), $agent);

    expect(Chunk::query()->where('chunkable_id', $website->id)->count())->toBe(2)
        ->and(DB::table('chunk_references')->where('referenceable_id', $url->id)->count())->toBe(2);
});

it('enhances the new parts before storing them when enhancement is on', function (): void {
    config(['chatvia.ai.chunker.enhance' => true, 'services.scalewayai.api_key' => 'test-key']);
    trainUrlFakes(['First chunk of the page.', 'Second chunk of the page.']);
    [$agent, $website, $url] = trainUrlFixture();
    Http::fake([
        'https://firecrawl.test/v2/scrape' => Http::response(['success' => true, 'data' => ['markdown' => 'Page text.', 'metadata' => []]]),
        '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'summary' => 'About the product.',
            'questions' => ['What does the product do?'],
        ])]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]),
    ]);

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    $chunks = Chunk::query()->ofSource($website)->get();

    expect($chunks)->toHaveCount(2)
        ->and($chunks->every(fn (Chunk $chunk): bool => $chunk->is_enhanced && $chunk->summary === 'About the product.'))->toBeTrue();
});

it('keeps the index a shared part got when it was first stored', function (): void {
    [$agent, $website, $url] = trainUrlFixture();
    trainUrlFakes(['Intro of the first page.', 'Shared footer.']);
    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    $other = $website->urls()->create(['url' => 'https://docs.example.test/other']);
    test()->mock(TextSplitter::class, function ($splitter): void {
        $splitter->shouldReceive('split')->andReturn(['Shared footer.']);
        $splitter->shouldReceive('name')->andReturn('builtin');
    });

    app(TrainWebsiteUrlAction::class)->execute($other, $agent);

    $footer = Chunk::query()->ofSource($website)->where('checksum', hash('sha256', 'Shared footer.'))->sole();

    expect($footer->chunk_index)->toBe(2)
        ->and(DB::table('chunk_references')->where('chunk_id', $footer->id)->count())->toBe(2);
});

it('drops the parts a changed page no longer has and deletes the chunks no page uses', function (): void {
    [$agent, $website, $url] = trainUrlFixture();
    trainUrlFakes(['Old part.', 'Shared part.']);
    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    // Another page shares one part, so that chunk must survive.
    $other = $website->urls()->create(['url' => 'https://docs.example.test/other']);
    $shared = Chunk::query()->ofSource($website)->where('checksum', hash('sha256', 'Shared part.'))->sole();
    DB::table('chunk_references')->insert(['chunk_id' => $shared->id, 'referenceable_type' => $other->getMorphClass(), 'referenceable_id' => $other->id]);
    $old = Chunk::query()->ofSource($website)->where('checksum', hash('sha256', 'Old part.'))->sole();

    // The page changed: it splits into other parts now.
    test()->mock(TextSplitter::class, function ($splitter): void {
        $splitter->shouldReceive('split')->andReturn(['Shared part.', 'New part.']);
        $splitter->shouldReceive('name')->andReturn('builtin');
    });
    $url->update(['checksum' => null]);
    Cache::flush();

    app(TrainWebsiteUrlAction::class)->execute($url->fresh(), $agent);

    expect(Chunk::query()->find($old->id))->toBeNull()
        ->and(Chunk::query()->find($shared->id))->not->toBeNull()
        ->and($url->chunks()->pluck('chunk')->sort()->values()->all())->toBe(['New part.', 'Shared part.'])
        ->and($other->chunks()->pluck('chunk')->all())->toBe(['Shared part.']);
});

it('reports the website progress it is given', function (): void {
    trainUrlFakes(['Only chunk.']);
    [$agent, , $url] = trainUrlFixture();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent, 50);

    Event::assertDispatched(WebsiteIngestionProgressed::class, fn (WebsiteIngestionProgressed $event): bool => $event->broadcastWith()['progress'] === 50);
});

it('trains an unchanged page again once the chunk settings change', function (array $change): void {
    trainUrlFakes(['First chunk of the page.']);
    [$agent, $website, $url] = trainUrlFixture();
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
    trainUrlFakes(['First chunk of the page.']);
    [$agent, $website, $url] = trainUrlFixture($metadata);

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    expect($url->fresh()->title)->toBe($title);
})->with([
    'the page title' => [['title' => ' Returns and refunds ', 'ogTitle' => 'OG title'], 'Returns and refunds'],
    'the Open Graph title' => [['ogTitle' => 'OG title'], 'OG title'],
    'none' => [[], null],
]);

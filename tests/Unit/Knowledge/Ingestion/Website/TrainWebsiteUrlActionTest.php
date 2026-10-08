<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Actions\Website\TrainWebsiteUrlAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlEnhanceJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * TrainWebsiteUrlAction turns one crawled page into chunks and links each
 * chunk to its URL in chunk_references. The link must use the morph alias
 * (the app reads that column through the enforced morph map), and a retrain
 * must not re-link or re-fetch chunks one by one.
 */
function trainUrlFakes(array $chunks): void
{
    $splitter = test()->mock(TextSplitter::class);
    $splitter->shouldReceive('split')->andReturn($chunks);
    $splitter->shouldReceive('name')->andReturn('chunkfit');

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

function trainUrlFixture(): array
{
    $agent = Agent::factory()->create();

    $website = SourceWebsite::create([
        'user_id' => $agent->user_id,
        'url' => 'https://docs.example.test',
        'status' => 'ingesting',
    ]);
    $agent->websites()->attach($website->id);

    $url = $website->urls()->create(['url' => 'https://docs.example.test/page']);

    config()->set('services.firecrawl.endpoint', 'https://firecrawl.test/v2');
    config()->set('services.firecrawl.key', 'fc-test');
    Http::fake(['https://firecrawl.test/v2/scrape' => Http::response([
        'success' => true,
        'data' => ['markdown' => '# Page'.PHP_EOL.'Some content about the product.', 'metadata' => []],
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

it('dispatches one enhance job per chunk only when enhancement is on', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);
    trainUrlFakes(['First chunk of the page.', 'Second chunk of the page.']);
    [$agent, , $url] = trainUrlFixture();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    Bus::assertDispatchedTimes(WebsiteUrlEnhanceJob::class, 2);
});

it('still finds links stored with the class name by an older release', function (): void {
    trainUrlFakes(['Only chunk.']);
    [$agent, , $url] = trainUrlFixture();

    app(TrainWebsiteUrlAction::class)->execute($url, $agent);

    DB::table('chunk_references')
        ->where('referenceable_id', $url->id)
        ->update(['referenceable_type' => AgentWebsiteUrl::class]);

    expect($url->chunks()->count())->toBe(1);
});

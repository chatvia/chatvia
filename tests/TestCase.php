<?php

namespace Tests;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeEmbeddingsProvider;
use Tests\Support\FakeReranker;
use Tests\Support\FakeTextSplitter;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests must not depend on the last `npm run build` — a page
        // added after the build would 500 with "Unable to locate file in Vite
        // manifest" even though the backend is correct.
        $this->withoutVite();

        // `testing` has no Scaleway API key, so bind a deterministic offline
        // embeddings provider by default — keeps the embedding paths (sync,
        // routing, function/KB indexing) fast + silent instead of tripping the
        // typed getApiKey(). A test needing specific vectors re-binds its own.
        $this->app->instance(EmbeddingsProviderInterface::class, new FakeEmbeddingsProvider);

        // Same for the reranker: an offline identity reranker keeps the rerank
        // paths (knowledge search, tool relevance gate) running without a key.
        $this->app->instance(Reranker::class, new FakeReranker);

        // And for the text splitter: a simple one keeps ingestion tests
        // independent of the chunking settings.
        $this->app->instance(TextSplitter::class, new FakeTextSplitter);

        // Chunk enhancement calls the Smart model for every chunk; tests that
        // cover it switch it on themselves.
        config(['chatvia.ai.chunker.enhance' => false]);

        // The seeded catalog is Scaleway AI's: its models can be used only
        // while that account has a key. Speech to text stays off, as it was
        // with no key; tests that cover it choose it themselves.
        config(['services.scalewayai.api_key' => 'test-key', 'chatvia.ai.transcriber' => 'none']);

        // The admin panel lists OpenRouter's rerankers live; no test reaches it.
        Http::fake(['openrouter.ai/api/v1/models?output_modalities=rerank*' => Http::response(['data' => []])]);

        // No test talks to a real service: a request no fake answers fails
        // the test instead of going out (and passing because the code
        // forgives the failed call).
        Http::preventStrayRequests();

        // Pages render without server-side rendering: with `npm run dev`
        // running, Inertia would post every page to the Vite dev server.
        config(['inertia.ssr.enabled' => false]);

        // The chat memory store is Redis in every environment. Tests use an
        // array store, so nothing outlives a test and none needs Redis.
        config(['cache.stores.chat' => ['driver' => 'array']]);
    }

    public function faker(): Generator
    {
        return Factory::create();
    }
}

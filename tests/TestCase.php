<?php

namespace Tests;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
    }

    public function faker(): Generator
    {
        return Factory::create();
    }
}

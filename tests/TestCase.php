<?php

namespace Tests;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Llm\Services\ModelsDevCatalog;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\ParallelTesting;
use Tests\Support\Database\ParallelTestDatabases;
use Tests\Support\Database\ProvisionTestDatabase;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;
use Tests\Support\Knowledge\Embedding\FakeReranker;
use Tests\Support\Knowledge\Ingestion\FakeTextSplitter;

abstract class TestCase extends BaseTestCase
{
    /**
     * The analytics tables sit on a connection of their own (set to the
     * testing database in phpunit.xml), a second session that needs its own
     * transaction to be rolled back after each test. Null is the default
     * connection.
     *
     * @var list<string|null>
     */
    protected array $connectionsToTransact = [null, 'analytics'];

    /**
     * In a parallel run each worker tests against its own database
     * (`testing_test_1`, …), a copy of the testing database made before the
     * workers start ({@see ParallelTestDatabases}).
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if ($token = $app->make(ParallelTesting::class)->token()) {
            $this->pointToolConnectionsAtWorkerDatabase($app, (string) $token);

            // Should Laravel still make a worker's database itself (its check
            // for it failed), build it like `testing`, not with a bare migrate.
            $app->make(ParallelTesting::class)->setUpTestDatabaseBeforeMigrating(fn (): mixed => (new ProvisionTestDatabase)());
        }

        return $app;
    }

    /**
     * Analytics, Pulse and Spectra have connections of their own, set to the testing
     * database (phpunit.xml). Laravel moves only the default connection to
     * the worker's database, so these follow it: otherwise every worker
     * would migrate and read the one shared database at once.
     */
    private function pointToolConnectionsAtWorkerDatabase(Application $app, string $token): void
    {
        $config = $app->make('config');
        $database = (string) $config->get('database.connections.'.$config->get('database.default').'.database');

        foreach (ParallelTestDatabases::CONNECTIONS_ON_THE_TESTING_DATABASE as $connection) {
            if ($config->get("database.connections.{$connection}.database") === $database) {
                $config->set("database.connections.{$connection}.database", ParallelTestDatabases::workerDatabase($database, $token));
            }
        }
    }

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

        // Models read their facts from the models.dev catalog, which is
        // always on. Each test starts with an empty one that never goes
        // stale, so none downloads it; tests that cover it call
        // forgetModelsDevCatalog() and fake the download.
        Cache::memo()->forever(ModelsDevCatalog::CACHE_KEY, ['checked_at' => now()->addYears(100)->getTimestamp()]);

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

        // Spectra stays installed (its dashboard routes are tested) but
        // records nothing, although its admin switch now starts on: its
        // watchers would otherwise write even faked AI calls to the testing
        // database. A test covering the recording switches it on itself.
        config(['spectra.enabled' => false, 'chatvia.spectra.recording' => false]);

        // New agents analyze sentiment, tone and topics with the model. Tests
        // start with that off, so a chat turn calls no unfaked model; a test
        // of the analysis turns it on for its agent.
        $analytics = 'model-settings.defaults.'.Agent::class.'.analytics';
        config(["{$analytics}.sentiment" => false, "{$analytics}.tone" => false, "{$analytics}.topics" => false]);
    }

    public function faker(): Generator
    {
        return Factory::create();
    }
}

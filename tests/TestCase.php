<?php

namespace Tests;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Cashier\Cashier;
use Tests\Support\FakeEmbeddingsProvider;
use Tests\Support\FakeReranker;

abstract class TestCase extends BaseTestCase
{
    /**
     * The suite tests the hosted product: paid plans on, sign-up through the
     * trial checkout. Billing is set in the admin panel, nothing is saved there
     * in the test database, and a new install has paid plans off. So turn them
     * on before the providers boot, as a saved setting would. This runs after
     * the booting callbacks of the app providers: they have just applied the
     * (empty) admin settings and left out Cashier's routes, which Cashier adds
     * while the providers boot. A test about billing off sets
     * `billing.enabled` itself.
     *
     * The parent loads bootstrap/app.php and boots it in one step, so this
     * repeats those lines to add the hook in between.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $this->traitsUsedByTest = class_uses_recursive(static::class);

        $app->afterBootstrapping(RegisterProviders::class, fn (Application $app) => $app->booting(function (): void {
            config(['billing.enabled' => true, 'billing.registration_enabled' => false]);
            Cashier::$registersRoutes = true;
        }));

        $app->make(Kernel::class)->bootstrap();

        return $app;
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
    }

    public function faker(): Generator
    {
        return Factory::create();
    }
}

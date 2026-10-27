<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Routing\SemanticToolRouter;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Spectra\Spectra;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * The router's AI calls are tagged `tool-routing` in Spectra, so its query
 * embedding and rerank are told apart from the knowledge search's.
 */
it('tags its calls as tool routing in Spectra', function (): void {
    config(['spectra.enabled' => true]);

    $embeddings = new class extends FakeEmbeddingsProvider
    {
        /** @var list<string> */
        public array $tags = [];

        public function embedQuery(string $text, bool $returnAsText = false): string|array
        {
            $this->tags = app(Spectra::class)->startRequest('cohere', 'embed-test')->tags;

            return parent::embedQuery($text, $returnAsText);
        }
    };
    app()->instance(EmbeddingsProvider::class, $embeddings);

    app(SemanticToolRouter::class)->route('refund this customer', Agent::factory()->create());

    expect($embeddings->tags)->toBe(['tool-routing']);
});

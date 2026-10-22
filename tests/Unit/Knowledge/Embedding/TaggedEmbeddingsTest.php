<?php

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\TaggedEmbeddings;
use Domain\Llm\Services\AiCallTags;
use Spectra\Spectra;

/**
 * The embedding provider the container hands out tags what it indexes
 * `embedding` and search queries `search-embedding`, and passes the rest
 * through to the provider.
 */
function tembProvider(): EmbeddingsProviderInterface
{
    return new class implements EmbeddingsProviderInterface
    {
        /** @var list<list<string>> The Spectra tags each call was sent with. */
        public array $tags = [];

        public function embed(string $text, bool $returnAsText = false): array
        {
            return $this->record([0.1]);
        }

        public function embedBatch(array $texts): array
        {
            return $this->record([[0.1]]);
        }

        public function embedQuery(string $text, bool $returnAsText = false): array
        {
            return $this->record([0.2]);
        }

        public function getDimensions(): int
        {
            return 1024;
        }

        public function getModelName(): string
        {
            return 'test-embed';
        }

        private function record(array $result): array
        {
            $this->tags[] = app(Spectra::class)->startRequest('openai', 'test-embed')->tags;

            return $result;
        }
    };
}

it('tags indexing and search embeddings apart', function (): void {
    config(['spectra.enabled' => true]);
    $provider = tembProvider();
    $embeddings = new TaggedEmbeddings($provider, new AiCallTags);

    $embeddings->embed('chunk');
    $embeddings->embedBatch(['chunk']);
    $embeddings->embedQuery('question');

    expect($provider->tags)->toBe([['embedding'], ['embedding'], ['search-embedding']]);
});

it('passes the vectors, size and model through', function (): void {
    $provider = tembProvider();
    $embeddings = new TaggedEmbeddings($provider, new AiCallTags);

    expect($embeddings->embed('chunk'))->toBe([0.1])
        ->and($embeddings->embedQuery('question'))->toBe([0.2])
        ->and($embeddings->getDimensions())->toBe(1024)
        ->and($embeddings->getModelName())->toBe('test-embed')
        ->and($embeddings->provider())->toBe($provider);
});

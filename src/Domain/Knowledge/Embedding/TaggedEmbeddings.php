<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Services\AiCallTags;

/**
 * The configured embedding provider, with its calls tagged in Spectra:
 * `embedding` for what is indexed (chunks, Q&A, tools, forms) and
 * `search-embedding` for search queries. Each provider sends its own
 * requests, so this wrapper is the one place that tags all of them. It is
 * what the container hands out for {@see EmbeddingsProviderInterface}.
 */
final readonly class TaggedEmbeddings implements EmbeddingsProviderInterface
{
    public function __construct(
        private EmbeddingsProviderInterface $provider,
        private AiCallTags $tags,
    ) {}

    /** The provider the calls go to. */
    public function provider(): EmbeddingsProviderInterface
    {
        return $this->provider;
    }

    public function embed(string $text, bool $returnAsText = false): string|array
    {
        return $this->tags->during(AiCallTag::Embedding, fn (): string|array => $this->provider->embed($text, $returnAsText));
    }

    public function embedBatch(array $texts): array
    {
        return $this->tags->during(AiCallTag::Embedding, fn (): array => $this->provider->embedBatch($texts));
    }

    public function embedQuery(string $text, bool $returnAsText = false): string|array
    {
        return $this->tags->during(AiCallTag::SearchEmbedding, fn (): string|array => $this->provider->embedQuery($text, $returnAsText));
    }

    public function getDimensions(): int
    {
        return $this->provider->getDimensions();
    }

    public function getModelName(): string
    {
        return $this->provider->getModelName();
    }
}

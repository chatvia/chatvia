<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingServiceMissingException;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * What the container hands out while the admin has chosen no embedding
 * service. Classes that need embeddings can still be built (the deploy step
 * runs before the setup); every call to embed fails with a clear message
 * instead of going to a service nobody picked.
 */
final readonly class UnconfiguredEmbeddings implements EmbeddingsProvider
{
    public function __construct(
        private EmbeddingStorage $storage,
    ) {}

    public function embed(string $text, bool $returnAsText = false): string|array
    {
        throw EmbeddingServiceMissingException::make();
    }

    public function embedBatch(array $texts): array
    {
        throw EmbeddingServiceMissingException::make();
    }

    public function embedQuery(string $text, bool $returnAsText = false): string|array
    {
        throw EmbeddingServiceMissingException::make();
    }

    /** The stored size, so building it never fails the size check. */
    public function getDimensions(): int
    {
        return $this->storage->dimensions();
    }

    public function getModelName(): string
    {
        return '';
    }
}

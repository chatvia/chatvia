<?php

namespace Domain\Knowledge\Embedding\Exceptions;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use RuntimeException;

/**
 * The configured embedding provider returns vectors of a different size than
 * the database stores. Raised when the provider is resolved, so a wrong setup
 * fails at once with a clear message instead of on the first insert or search.
 */
class EmbeddingDimensionsMismatchException extends RuntimeException
{
    public static function for(EmbeddingsProviderInterface $provider, int $stored): self
    {
        return new self(sprintf(
            'The embedding model "%s" returns %d dimensions, but the database stores %d (EMBEDDING_DIMENSIONS). Choose a provider that can return %d dimensions.',
            $provider->getModelName(),
            $provider->getDimensions(),
            $stored,
            $stored,
        ));
    }
}

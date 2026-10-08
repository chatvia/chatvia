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
    public static function for(EmbeddingsProviderInterface $provider): self
    {
        return new self(sprintf(
            'The embedding model "%s" returns %d dimensions, but the database stores %d. Choose a model or setting with %d dimensions.',
            $provider->getModelName(),
            $provider->getDimensions(),
            EmbeddingsProviderInterface::STORED_DIMENSIONS,
            EmbeddingsProviderInterface::STORED_DIMENSIONS,
        ));
    }
}

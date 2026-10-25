<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Exceptions;

use RuntimeException;

/**
 * No embedding service is chosen in the admin panel yet (Settings → AI
 * models), so nothing can be embedded. Callers that run before the setup
 * (the deploy step that catalogs the built-in tools) stop quietly on it.
 */
class EmbeddingServiceMissingException extends RuntimeException
{
    public static function make(): self
    {
        return new self('No embedding service is set up yet. Choose one in the admin panel: Settings → AI models.');
    }
}

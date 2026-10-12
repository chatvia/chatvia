<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Domain\Knowledge\Ingestion\Enums\ChunkerStrategy;
use Spatie\LaravelData\Data;

/**
 * What a chunker server can do (GET /v1/engines): the strategies it runs, the
 * tokenizers it counts with, and whether it has an embeddings model for the
 * semantic ones. `error` is set when the server could not be asked; then
 * nothing is known about it.
 */
final class ChunkerServerData extends Data
{
    /**
     * @param  list<string>  $strategies  `engine.strategy` values ({@see ChunkerStrategy}).
     * @param  list<string>  $encodings  The tokenizers it can count with.
     */
    public function __construct(
        public array $strategies = [],
        public array $encodings = [],
        public bool $embeddingsConfigured = false,
        public ?string $embeddingsModel = null,
        public ?string $error = null,
    ) {}

    public static function unreachable(string $error): self
    {
        return new self(error: $error);
    }

    public function isReachable(): bool
    {
        return $this->error === null;
    }

    /**
     * Why the server cannot run a strategy, or null when it can (or when the
     * server could not be asked).
     */
    public function cannotRun(ChunkerStrategy $strategy): ?string
    {
        if (! $this->isReachable()) {
            return null;
        }

        if (! in_array($strategy->value, $this->strategies, true)) {
            return 'Not offered by your chunker. Update it.';
        }

        if ($strategy->requiresEmbeddings() && ! $this->embeddingsConfigured) {
            return 'Needs an embeddings model on the chunker (EMBEDDINGS_MODEL).';
        }

        return null;
    }
}

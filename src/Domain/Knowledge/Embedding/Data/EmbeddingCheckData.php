<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Data;

use Domain\Knowledge\Embedding\Enums\EmbeddingCheckStatus;
use Spatie\LaravelData\Data;

/**
 * The result of a test embedding: how it went, the vector size it returned
 * and the HTTP status of a failed call.
 */
final class EmbeddingCheckData extends Data
{
    public function __construct(
        public EmbeddingCheckStatus $status,
        public ?int $returned = null,
        public ?int $httpStatus = null,
    ) {}

    public function ok(): bool
    {
        return $this->status === EmbeddingCheckStatus::Ok;
    }

    /** Whether the service accepted the key, whatever the model did. */
    public function keyAccepted(): bool
    {
        return ! collect([EmbeddingCheckStatus::KeyRefused, EmbeddingCheckStatus::Unreachable])->containsStrict($this->status);
    }

    /** What went wrong, in plain words, or null when it worked. */
    public function message(string $label, ?int $stored = null): ?string
    {
        return match ($this->status) {
            EmbeddingCheckStatus::Ok => null,
            EmbeddingCheckStatus::KeyRefused => "{$label} refused the API key. Check that you copied all of it.",
            EmbeddingCheckStatus::ModelFailed => "{$label} could not use this model ({$this->httpStatus}). Check the model's name.",
            EmbeddingCheckStatus::Unreachable => "{$label} could not be reached. Try again.",
            EmbeddingCheckStatus::Empty => "{$label} returned no vector. Check the model's name.",
            EmbeddingCheckStatus::WrongSize => "This model returned {$this->returned} dimensions, but the database stores {$stored}. Use a model that returns {$stored}.",
        };
    }
}

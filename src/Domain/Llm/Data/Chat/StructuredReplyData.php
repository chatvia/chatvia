<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Chat;

use Domain\Llm\Exceptions\InvalidStructuredReplyException;
use Spatie\LaravelData\Data;

/**
 * A chat reply that was asked to be JSON: the decoded value (null when the
 * reply held no usable JSON) next to the full reply, for its usage.
 */
final class StructuredReplyData extends Data
{
    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public function __construct(
        public ?array $data,
        public ChatResponseData $response,
    ) {}

    /**
     * The decoded JSON, for callers that cannot go on without it.
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidStructuredReplyException
     */
    public function requireData(): array
    {
        return $this->data ?? throw new InvalidStructuredReplyException;
    }
}

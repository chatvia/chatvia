<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

use Spatie\LaravelData\Data;

/**
 * Token counts and cost of one assistant reply, as far as they are known.
 * Inline stream usage carries tokens but no cost; a provider lookup can carry
 * both; when neither is available everything is null and the stored message
 * falls back to an estimate for the workspace's token log.
 */
class ReplyUsageData extends Data
{
    public function __construct(
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $costInMicroCents = null,
    ) {}

    /** The billed token total, or 0 when the provider reported nothing. */
    public function totalTokens(): int
    {
        return ($this->promptTokens ?? 0) + ($this->completionTokens ?? 0);
    }
}

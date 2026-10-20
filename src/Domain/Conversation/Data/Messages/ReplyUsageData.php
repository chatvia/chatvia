<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

use Spatie\LaravelData\Data;

/**
 * Token counts of one assistant reply, as far as they are known: from the
 * stream, or from a provider lookup. When neither has them both are null and
 * the stored message falls back to an estimate for the workspace's token log.
 */
class ReplyUsageData extends Data
{
    public function __construct(
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}

    /** The billed token total, or 0 when the provider reported nothing. */
    public function totalTokens(): int
    {
        return ($this->promptTokens ?? 0) + ($this->completionTokens ?? 0);
    }
}

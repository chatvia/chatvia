<?php

namespace Domain\Integration\Mcp\Exceptions;

use RuntimeException;

/**
 * Raised when marking a connector "always on" would push the agent's
 * always-shipped tool count past the cap. Every always-on tool rides along
 * on every turn, so the cap keeps the per-turn tool payload sane.
 */
class AlwaysAvailableToolLimitException extends RuntimeException
{
    public static function reached(int $limit, int $wouldBe): self
    {
        return new self("Always-on connectors can ship at most {$limit} tools on every turn; this would make it {$wouldBe}.");
    }
}

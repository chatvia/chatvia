<?php

declare(strict_types=1);

namespace Tests\Support\Conversation\Tooling;

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolName;

/**
 * An approval policy without the agent's stored settings: it gates every
 * tool, none, or exactly the named ones.
 */
final class FakeToolApprovalPolicy extends ToolApprovalPolicy
{
    /** @param  list<string>|null  $gated  the gated tool names; null gates every tool */
    private function __construct(private readonly ?array $gated)
    {
        // No parent constructor: nothing is looked up.
    }

    public static function gatingEverything(): self
    {
        return new self(null);
    }

    public static function gatingNothing(): self
    {
        return new self([]);
    }

    public static function gating(string ...$names): self
    {
        return new self(array_values($names));
    }

    public function requiresApproval(Agent $agent, ToolName $tool): bool
    {
        return $this->gated === null || in_array($tool->value, $this->gated, true);
    }
}

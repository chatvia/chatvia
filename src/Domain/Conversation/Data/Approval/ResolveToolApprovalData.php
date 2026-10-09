<?php

namespace Domain\Conversation\Data\Approval;

use Domain\Conversation\Enums\ToolApprovalDecision;
use Spatie\LaravelData\Data;

/**
 * The person's decision for each gated tool call of a paused turn, keyed by
 * the tool call id. Calls without a decision are treated as denied.
 */
class ResolveToolApprovalData extends Data
{
    /**
     * @param  array<string, ToolApprovalDecision>  $decisions
     */
    public function __construct(
        public array $decisions,
    ) {}
}

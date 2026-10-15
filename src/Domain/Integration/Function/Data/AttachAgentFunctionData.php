<?php

declare(strict_types=1);

namespace Domain\Integration\Function\Data;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Spatie\LaravelData\Data;

/**
 * Inputs for attaching an account-level function to an agent: the agent and
 * one of its workspace's functions.
 */
class AttachAgentFunctionData extends Data
{
    public function __construct(
        public Agent $agent,
        public AgentFunction $function,
    ) {}
}

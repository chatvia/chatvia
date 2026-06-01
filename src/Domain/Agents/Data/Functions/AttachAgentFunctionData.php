<?php

namespace Domain\Agents\Data\Functions;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Inputs for attaching an account-level function to an agent. `function_id` is
 * the function's `external_id` (not the internal primary key) — it's what the
 * API surface exposes to clients.
 */
class AttachAgentFunctionData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $function_id,
    ) {}
}

<?php

namespace Domain\Agents\Observers;

use Domain\Agents\Models\AgentFunction;
use Domain\Chat\Services\Tooling\ToolRegistry;

class AgentFunctionObserver
{
    public function __construct(private readonly ToolRegistry $registry) {}

    public function created(AgentFunction $function): void
    {
        $function->agent()->increment('functions_count');
        $this->registry->forget($function->agent_id);
    }

    public function updated(AgentFunction $function): void
    {
        $this->registry->forget($function->agent_id);
    }

    public function deleted(AgentFunction $function): void
    {
        // decrement only if positive — prevents drift on already-zero counters
        $function->agent()->where('functions_count', '>', 0)->decrement('functions_count');
        $this->registry->forget($function->agent_id);
    }
}

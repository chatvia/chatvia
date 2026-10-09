<?php

namespace Domain\Integration\Function\Observers;

use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Jobs\EmbedFunctionJob;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * Functions are workspace-level and attach to agents through the
 * `agent_function` pivot, so one function change can affect many agents:
 * every attached agent's cached tool pool is dropped when it changes or is
 * deleted.
 *
 * Also drives the semantic-router embedding refresh: any time the name or
 * description changes (the fields the router scores against), a queued job
 * re-embeds so the row scores correctly on the next turn.
 */
class AgentFunctionObserver
{
    public function __construct(private readonly ToolRegistry $registry) {}

    public function created(AgentFunction $function): void
    {
        EmbedFunctionJob::dispatch($function->id);
    }

    public function updated(AgentFunction $function): void
    {
        if ($function->wasChanged(['internal_name', 'description'])) {
            EmbedFunctionJob::dispatch($function->id);
        }

        $this->registry->forgetAttachedTo($function);
    }

    /**
     * The database removes the pivot rows together with the function
     * (cascade), so the attached agents are read here, before the delete.
     */
    public function deleting(AgentFunction $function): void
    {
        $function->load('agents:agents.id');
    }

    public function deleted(AgentFunction $function): void
    {
        $this->registry->forgetMany($function->agents->modelKeys());
    }
}

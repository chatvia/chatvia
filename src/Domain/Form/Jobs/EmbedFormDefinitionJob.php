<?php

namespace Domain\Form\Jobs;

use Domain\Agent\Models\Agent;
use Domain\Form\Actions\EmbedFormDefinitionAction;
use Domain\Form\Models\FormDefinition;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds one form definition off the request path, so the find-forms tool can
 * match it to what a visitor asks. Its token cost is booked to the workspace
 * that owns the form's agent.
 */
class EmbedFormDefinitionJob implements AttributesTokenUsage, ShouldQueue
{
    use Queueable;
    use TracksTokenUsage;

    public function __construct(public int $formId) {}

    public function handle(EmbedFormDefinitionAction $action): void
    {
        $action->execute($this->formId);
    }

    public function tokenWorkspaceId(): ?int
    {
        // Forms are agent-scoped (no own user_id); the workspace is the agent's
        // owner, so the embedding token cost lands on the right account.
        $agentId = FormDefinition::query()->whereKey($this->formId)->value('agent_id');
        if ($agentId === null) {
            return null;
        }

        $userId = Agent::query()->whereKey($agentId)->value('user_id');

        return $userId !== null ? (int) $userId : null;
    }
}

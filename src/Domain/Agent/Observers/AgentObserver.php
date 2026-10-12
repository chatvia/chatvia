<?php

namespace Domain\Agent\Observers;

use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Services\ModelRoles;

/**
 * Agent lifecycle hooks that must run on every insert: seed the sign-in
 * secret and the default LLM. Deleting an agent with its data is not a hook —
 * it is {@see DeleteAgentAction} (hide at once) and the queued purge after it.
 */
class AgentObserver
{
    public function __construct(
        private readonly ModelRoles $models,
    ) {}

    /**
     * Seed the JWT signing secret as part of the initial insert so the
     * read-only identity/preview render paths never trigger a lazy write
     * ({@see Agent::ensureAuthSecret()}). Rotation stays explicit via
     * {@see Agent::regenerateAuthSecret()}.
     */
    public function creating(Agent $agent): void
    {
        if (empty($agent->auth_secret)) {
            $agent->auth_secret = Agent::generateAuthSecret();
        }
    }

    /**
     * Seed the agent's LLM when none was given.
     */
    public function created(Agent $agent): void
    {
        // Each agent answers with a single LLM: the chat model picked in
        // Settings → AI (LlmRole::Chat), else the first usable model of the
        // catalog. Operators can swap it in Setup later.
        if ($agent->llm_id === null) {
            $llmId = $this->models->find(LlmRole::Chat)?->id;

            if ($llmId !== null) {
                $agent->forceFill(['llm_id' => $llmId])->save();
            }
        }
    }
}

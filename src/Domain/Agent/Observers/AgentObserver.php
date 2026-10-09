<?php

namespace Domain\Agent\Observers;

use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;

/**
 * Agent lifecycle hooks that must run on every insert: seed the sign-in
 * secret and the default LLM. Deleting an agent with its data is not a hook —
 * it is {@see DeleteAgentAction}, so it runs in one transaction with the
 * storage and cache cleanup after the commit.
 */
class AgentObserver
{
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
        // Each agent answers with a single LLM. Seed it to the platform's
        // configured default (config('chatvia.agent.default_model'), matched by
        // `llms.internal_name`); fall back to the first available row if the
        // configured name isn't in the catalog. Operators can swap it in
        // Setup later.
        if ($agent->llm_id === null) {
            $llms = Llm::catalog();
            $defaultName = (string) config('chatvia.agent.default_model');
            $llmId = $llms->firstWhere('internal_name', $defaultName)?->id ?? $llms->first()?->id;

            if ($llmId !== null) {
                $agent->forceFill(['llm_id' => $llmId])->save();
            }
        }
    }
}

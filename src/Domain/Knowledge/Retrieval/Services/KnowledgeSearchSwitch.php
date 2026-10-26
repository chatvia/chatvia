<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Retrieval\Data\KnowledgeSearchStateData;

/**
 * Whether an agent searches its knowledge on a turn. The admin turns it on or
 * off for the whole install (Settings → Search), and may let each agent turn
 * it off for itself, for an agent that only uses tools. An agent's own switch
 * is kept but ignored while the admin does not let agents choose.
 *
 * With search off a turn runs no follow-up rewrite, Q&A match or chunk
 * search, gets no grounding rules, and a voice call gets no search tool.
 */
final class KnowledgeSearchSwitch
{
    /** Whether knowledge search is on for the install. */
    public function enabled(): bool
    {
        return filter_var(config('chatvia.vector.search.enabled', true), FILTER_VALIDATE_BOOL);
    }

    /** Whether each agent may turn knowledge search off for itself. */
    public function agentsMayChoose(): bool
    {
        return filter_var(config('chatvia.vector.search.agent_choice', true), FILTER_VALIDATE_BOOL);
    }

    /** Whether this agent searches its knowledge. */
    public function for(Agent $agent): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return ! $this->agentsMayChoose() || $this->agentChoice($agent);
    }

    /** What the agent's Sources page shows: the switch, or why there is none. */
    public function state(Agent $agent): KnowledgeSearchStateData
    {
        return new KnowledgeSearchStateData(
            installEnabled: $this->enabled(),
            canChange: $this->enabled() && $this->agentsMayChoose(),
            enabled: $this->for($agent),
        );
    }

    /** The agent's own switch; on unless the agent turned it off. */
    private function agentChoice(Agent $agent): bool
    {
        return filter_var($agent->settings('knowledge')->get('search') ?? true, FILTER_VALIDATE_BOOL);
    }
}

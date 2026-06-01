<?php

namespace Domain\Agents\Actions\Settings;

use Domain\Agents\Models\Agent;

class SetAgentStartersAction
{
    /**
     * Replace an agent's conversation starters with the given ordered list.
     *
     * @param  array<int, string>  $texts
     */
    public function execute(Agent $agent, array $texts): void
    {
        $agent->starterSet()->updateOrCreate([], ['text' => array_values($texts)]);
    }
}

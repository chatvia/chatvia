<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentTemplate;
use InvalidArgumentException;

class AttachAgentTemplateAction
{
    /**
     * Attach a template to an agent. Pure read-through — nothing is copied; the
     * agent simply starts reading its config from the template at runtime.
     */
    public function execute(Agent $agent, AgentTemplate $template): void
    {
        if ($template->user_id !== $agent->user_id) {
            throw new InvalidArgumentException('Template does not belong to the agent owner.');
        }

        $agent->agent_template_id = $template->id;
        $agent->save();
    }
}

<?php

namespace Domain\Sources\Web\Actions\Url;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Models\AgentWebsiteUrl;

class RetrainAgentWebsiteUrlAction
{
    public function execute(AgentWebsiteUrl $link, Agent $agent): void
    {
        $link->train($agent);
    }
}

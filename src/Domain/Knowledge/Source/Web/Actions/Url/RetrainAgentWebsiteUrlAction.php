<?php

namespace Domain\Knowledge\Source\Web\Actions\Url;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;

class RetrainAgentWebsiteUrlAction
{
    public function execute(AgentWebsiteUrl $link, Agent $agent): void
    {
        $link->train($agent);
    }
}

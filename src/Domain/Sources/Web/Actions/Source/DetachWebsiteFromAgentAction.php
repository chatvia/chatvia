<?php

namespace Domain\Sources\Web\Actions\Source;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Models\SourceWebsite;

class DetachWebsiteFromAgentAction
{
    public function execute(Agent $agent, SourceWebsite $website): void
    {
        $agent->websites()->detach($website->id);
    }
}

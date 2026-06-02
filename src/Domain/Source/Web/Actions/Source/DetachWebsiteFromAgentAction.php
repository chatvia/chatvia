<?php

namespace Domain\Source\Web\Actions\Source;

use Domain\Agent\Models\Agent;
use Domain\Source\Web\Models\SourceWebsite;

class DetachWebsiteFromAgentAction
{
    public function execute(Agent $agent, SourceWebsite $website): void
    {
        $agent->websites()->detach($website->id);
    }
}

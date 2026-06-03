<?php

namespace Domain\Knowledge\Source\Web\Actions\Source;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

class AttachWebsiteToAgentAction
{
    public function execute(Agent $agent, SourceWebsite $website): bool
    {
        if ($agent->websites()->where('source_websites.id', $website->id)->exists()) {
            return false;
        }

        $agent->websites()->attach($website->id);

        return true;
    }
}

<?php

namespace Domain\Sources\Web\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Models\SourceWebsite;

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

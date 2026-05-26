<?php

namespace Domain\Sources\Web\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Models\SourceWebsite;

class RetrainSourceWebsiteAction
{
    public function execute(SourceWebsite $website, Agent $agent, bool $recrawl): void
    {
        if ($recrawl) {
            $website->startCrawling($agent->id);
        } else {
            $website->startTraining($agent->id);
        }
    }
}

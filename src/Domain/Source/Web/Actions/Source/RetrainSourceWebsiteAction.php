<?php

namespace Domain\Source\Web\Actions\Source;

use Domain\Agent\Models\Agent;
use Domain\Source\Web\Models\SourceWebsite;

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

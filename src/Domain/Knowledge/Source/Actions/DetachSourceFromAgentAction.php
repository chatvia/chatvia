<?php

namespace Domain\Knowledge\Source\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Stops an agent from using a source without deleting it: other agents of the
 * workspace may still use it, and it stays in the shared pool.
 */
class DetachSourceFromAgentAction
{
    public function execute(Agent $agent, SourceText|SourceWebsite $source): void
    {
        $source->agents()->detach($agent->id);
    }
}

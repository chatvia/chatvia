<?php

namespace Domain\Knowledge\Source\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Lets an agent use a source the workspace already trained (shared pool): adds
 * the `agent_sources` row. The chunks are shared, so nothing is re-ingested.
 */
class AttachSourceToAgentAction
{
    /**
     * @return bool false when the agent already uses the source
     */
    public function execute(Agent $agent, SourceFile|SourceText|SourceWebsite $source): bool
    {
        if ($source->agents()->whereKey($agent->id)->exists()) {
            return false;
        }

        $source->agents()->attach($agent->id);

        return true;
    }
}

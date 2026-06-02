<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;

class UpdateAgentSecurityAction
{
    public function execute(Agent $agent, bool $isPublic): void
    {
        $agent->update(['is_public' => $isPublic]);
    }
}

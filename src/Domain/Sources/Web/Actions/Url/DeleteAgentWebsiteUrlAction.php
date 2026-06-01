<?php

namespace Domain\Sources\Web\Actions\Url;

use Domain\Sources\Web\Models\AgentWebsiteUrl;

class DeleteAgentWebsiteUrlAction
{
    public function execute(AgentWebsiteUrl $link): void
    {
        $link->chunks()->delete();
        $link->delete();
    }
}

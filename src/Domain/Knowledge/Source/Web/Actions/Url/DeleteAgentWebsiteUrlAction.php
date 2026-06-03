<?php

namespace Domain\Knowledge\Source\Web\Actions\Url;

use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;

class DeleteAgentWebsiteUrlAction
{
    public function execute(AgentWebsiteUrl $link): void
    {
        $link->chunks()->delete();
        $link->delete();
    }
}

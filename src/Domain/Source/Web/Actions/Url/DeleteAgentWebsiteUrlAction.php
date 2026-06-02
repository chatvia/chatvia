<?php

namespace Domain\Source\Web\Actions\Url;

use Domain\Source\Web\Models\AgentWebsiteUrl;

class DeleteAgentWebsiteUrlAction
{
    public function execute(AgentWebsiteUrl $link): void
    {
        $link->chunks()->delete();
        $link->delete();
    }
}

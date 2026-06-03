<?php

namespace Domain\Knowledge\Source\Text\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;

class DetachSourceTextFromAgentAction
{
    public function execute(Agent $agent, SourceText $text): void
    {
        $agent->texts()->detach($text->id);
    }
}

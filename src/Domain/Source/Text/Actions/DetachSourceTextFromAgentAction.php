<?php

namespace Domain\Source\Text\Actions;

use Domain\Agent\Models\Agent;
use Domain\Source\Text\Models\SourceText;

class DetachSourceTextFromAgentAction
{
    public function execute(Agent $agent, SourceText $text): void
    {
        $agent->texts()->detach($text->id);
    }
}

<?php

namespace Domain\Sources\Text\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Models\SourceText;

class DetachSourceTextFromAgentAction
{
    public function execute(Agent $agent, SourceText $text): void
    {
        $agent->texts()->detach($text->id);
    }
}

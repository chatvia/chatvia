<?php

namespace Domain\Sources\Text\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Models\SourceText;

class DeleteAgentSourceTextAction
{
    public function execute(Agent $agent, SourceText $text): void
    {
        $text->agents()->detach($agent->id);
        $text->delete();
        $text->chunks()->delete();
    }
}

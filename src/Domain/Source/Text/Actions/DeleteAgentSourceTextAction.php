<?php

namespace Domain\Source\Text\Actions;

use Domain\Agent\Models\Agent;
use Domain\Source\Text\Models\SourceText;

class DeleteAgentSourceTextAction
{
    public function execute(Agent $agent, SourceText $text): void
    {
        $text->agents()->detach($agent->id);
        $text->delete();
        $text->chunks()->delete();
    }
}

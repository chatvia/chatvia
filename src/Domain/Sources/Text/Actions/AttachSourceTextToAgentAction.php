<?php

namespace Domain\Sources\Text\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Models\SourceText;

class AttachSourceTextToAgentAction
{
    /**
     * Returns false when already attached (idempotent).
     */
    public function execute(Agent $agent, SourceText $text): bool
    {
        if ($agent->texts()->where('source_texts.id', $text->id)->exists()) {
            return false;
        }

        $agent->texts()->attach($text->id);

        return true;
    }
}

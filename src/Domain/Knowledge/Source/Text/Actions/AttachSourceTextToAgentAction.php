<?php

namespace Domain\Knowledge\Source\Text\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;

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

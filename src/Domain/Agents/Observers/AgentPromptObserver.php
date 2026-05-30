<?php

namespace Domain\Agents\Observers;

use Domain\Agents\Models\AgentPrompt;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Facades\Cache;

/**
 * Editing or deleting a library prompt must propagate to templated agents: the
 * prompt text is eager-loaded into each referencing template's cached payload,
 * so forget those entries to force a re-resolve on the next read.
 */
class AgentPromptObserver
{
    public function updated(AgentPrompt $prompt): void
    {
        $this->forgetReferencingTemplates($prompt);
    }

    public function deleted(AgentPrompt $prompt): void
    {
        $this->forgetReferencingTemplates($prompt);
    }

    private function forgetReferencingTemplates(AgentPrompt $prompt): void
    {
        AgentTemplate::where('agent_prompt_id', $prompt->id)
            ->pluck('id')
            ->each(fn (int $id) => Cache::forget(AgentTemplate::cacheKey($id)));
    }
}

<?php

namespace Domain\Agents\Observers;

use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps the per-template read-through cache (Agent::resolvedTemplate) honest:
 * any save or delete forgets the cached payload so attached agents pick up the
 * change on their next read — the "edit once → all agents update" promise.
 */
class AgentTemplateObserver
{
    public function saved(AgentTemplate $template): void
    {
        Cache::forget(AgentTemplate::cacheKey($template->id));
    }

    public function deleted(AgentTemplate $template): void
    {
        Cache::forget(AgentTemplate::cacheKey($template->id));
    }
}

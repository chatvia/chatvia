<?php

declare(strict_types=1);

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\ModelRoles;

/**
 * The model an agent answers with: its own, or the default chat model
 * (Settings → AI models) while it has none, as an agent made before any
 * model was added has. Null only when no model can answer at all.
 */
final readonly class AgentModel
{
    public function __construct(
        private ModelRoles $roles,
    ) {}

    public function for(Agent $agent): ?Llm
    {
        return $agent->llm ?? $this->roles->find(LlmRole::Chat);
    }
}

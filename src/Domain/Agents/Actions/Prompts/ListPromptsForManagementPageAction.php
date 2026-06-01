<?php

namespace Domain\Agents\Actions\Prompts;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\Prompts\AgentPromptRowData;
use Domain\Agents\Models\AgentPrompt;
use Illuminate\Support\Collection;

/**
 * List the user's reusable prompts for the Settings → Prompts page (each row
 * carries the per-prompt agent attachment count).
 *
 * @return Collection<int, AgentPromptRowData>
 */
class ListPromptsForManagementPageAction
{
    /**
     * @return Collection<int, AgentPromptRowData>
     */
    public function execute(User $user): Collection
    {
        return $user->prompts()
            ->withCount('agents')
            ->latest()
            ->get()
            ->map(fn (AgentPrompt $prompt): AgentPromptRowData => AgentPromptRowData::fromModel($prompt));
    }
}

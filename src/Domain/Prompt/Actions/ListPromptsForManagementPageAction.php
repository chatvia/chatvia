<?php

namespace Domain\Prompt\Actions;

use App\Workspace\Models\User;
use Domain\Prompt\Data\Resources\AgentPromptRowData;
use Domain\Prompt\Models\AgentPrompt;
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

<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;

class DeletePromptAction
{
    /**
     * Delete a library prompt. Any agents referencing it have their
     * `agent_prompt_id` nulled by the FK (nullOnDelete), so they fall back to
     * their own system_instructions / the built-in default.
     */
    public function execute(User $user, string $externalId): void
    {
        $user->prompts()
            ->where('external_id', $externalId)
            ->delete();
    }
}

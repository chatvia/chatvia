<?php

namespace Domain\Prompt\Actions;

use App\Identity\Workspace\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Translate a reusable prompt's external id (as it appears in the API) into
 * the internal primary key, scoped to the workspace. Returns null when no id
 * is supplied; throws when the id doesn't match a prompt the workspace owns.
 * Accepts the owner or a teammate: both resolve to the same workspace.
 */
class ResolveAgentPromptIdAction
{
    public function execute(User $owner, ?string $promptExternalId): ?int
    {
        if ($promptExternalId === null || $promptExternalId === '') {
            return null;
        }

        $prompt = $owner->workspace()->prompts()->where('external_id', $promptExternalId)->first();

        if (! $prompt) {
            throw ValidationException::withMessages(['prompt_id' => 'Prompt not found.']);
        }

        return $prompt->id;
    }
}

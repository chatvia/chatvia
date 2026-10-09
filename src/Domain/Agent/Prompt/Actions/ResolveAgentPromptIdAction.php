<?php

namespace Domain\Agent\Prompt\Actions;

use App\Identity\Workspace\Models\User;
use Support\Exceptions\DomainValidationException;

/**
 * Translate a reusable prompt's external id (as it appears in the API) into
 * the internal primary key, scoped to the workspace. Returns null when no id
 * is supplied; rejects an id the workspace does not own as a `prompt_id`
 * field error. Accepts the owner or a teammate: both resolve to the same
 * workspace.
 */
class ResolveAgentPromptIdAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(User $owner, ?string $promptExternalId): ?int
    {
        if ($promptExternalId === null || $promptExternalId === '') {
            return null;
        }

        $promptId = $owner->workspace()->prompts()->where('external_id', $promptExternalId)->value('id');

        return $promptId === null
            ? throw new DomainValidationException('prompt_id', __('Prompt not found.'))
            : (int) $promptId;
    }
}

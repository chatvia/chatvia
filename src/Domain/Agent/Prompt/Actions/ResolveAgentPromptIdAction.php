<?php

namespace Domain\Agent\Prompt\Actions;

use App\Identity\Workspace\Models\Workspace;
use Support\Exceptions\DomainValidationException;

/**
 * Translate a reusable prompt's external id (as it appears in the API) into
 * the internal primary key, scoped to the workspace. Returns null when no id
 * is supplied; rejects an id the workspace does not own as a `prompt_id`
 * field error.
 */
class ResolveAgentPromptIdAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Workspace $workspace, ?string $promptExternalId): ?int
    {
        if (blank($promptExternalId)) {
            return null;
        }

        $promptId = $workspace->prompts()->where('external_id', $promptExternalId)->value('id');

        return $promptId === null
            ? throw new DomainValidationException('prompt_id', __('Prompt not found.'))
            : (int) $promptId;
    }
}

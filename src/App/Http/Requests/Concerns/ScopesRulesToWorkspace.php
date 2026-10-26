<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Identity\Workspace\Models\User;

/**
 * The workspace a form request's `exists`/`unique` rules look in: the signed-in
 * person's. The API docs generator also calls `rules()` with no one signed in,
 * to describe each body; then the id is 0, which matches no row, so the rules
 * still build (and get documented) and can never pass for another workspace.
 */
trait ScopesRulesToWorkspace
{
    protected function ruleWorkspaceId(): int
    {
        $user = $this->user();

        return $user instanceof User ? $user->workspace()->id : 0;
    }
}

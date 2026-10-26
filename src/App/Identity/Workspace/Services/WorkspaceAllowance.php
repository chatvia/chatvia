<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\Workspace;
use Support\License\Entitlements;

/**
 * Whether this install may hold one more workspace. Without a license key it
 * holds one (a free-use limit): sign-up closes once it exists, and people
 * join it by invitation. Workspaces made while the install had a key stay
 * usable after it lapses; only new ones are refused.
 */
class WorkspaceAllowance
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function canCreate(): bool
    {
        $limit = $this->entitlements->workspaceLimit();

        return $limit === null || Workspace::query()->count() < $limit;
    }

    /** Whether the sign-up form is open: the admin switch (Settings → Workspaces) and the workspace limit. */
    public function signUpOpen(): bool
    {
        return (bool) (config('chatvia.registration_enabled') ?? true) && $this->canCreate();
    }
}

<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Services;

use App\Identity\Workspace\Models\Workspace;
use Support\License\Entitlements;

/**
 * Whether a workspace may create one more team. Without a license key a
 * workspace keeps only the team it starts with (a free-use limit); teams made
 * while the install had a key stay usable after it lapses, only new ones are
 * refused.
 */
class TeamAllowance
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function canCreate(Workspace $workspace): bool
    {
        $limit = $this->entitlements->teamLimit();

        return $limit === null || $workspace->teams()->count() < $limit;
    }
}

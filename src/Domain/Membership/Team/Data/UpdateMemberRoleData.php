<?php

namespace Domain\Membership\Team\Data;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;

/**
 * Input for moving a team member to another role, by role code.
 */
class UpdateMemberRoleData extends Data
{
    public function __construct(
        public Team $team,
        public User $member,
        public string $roleCode,
    ) {}
}

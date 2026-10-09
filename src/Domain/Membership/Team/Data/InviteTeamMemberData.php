<?php

namespace Domain\Membership\Team\Data;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;

/**
 * Input for adding someone to a team under a role code: either an existing
 * managed user (`userId`) or an email address to invite.
 */
class InviteTeamMemberData extends Data
{
    public function __construct(
        public Team $team,
        public User $inviter,
        public string $role,
        public ?int $userId = null,
        public ?string $email = null,
    ) {}
}

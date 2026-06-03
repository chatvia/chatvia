<?php

namespace Domain\Membership\Team\Data;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;

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

<?php

namespace Domain\Team\Data;

use App\Workspace\Models\User;
use App\Team\Models\Team;
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

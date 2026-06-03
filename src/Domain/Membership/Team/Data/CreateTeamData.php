<?php

namespace Domain\Membership\Team\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class CreateTeamData extends Data
{
    public function __construct(
        public User $owner,
        public string $name,
    ) {}
}

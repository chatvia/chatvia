<?php

namespace Domain\Team\Data;

use App\Workspace\Models\User;
use Spatie\LaravelData\Data;

class CreateTeamData extends Data
{
    public function __construct(
        public User $owner,
        public string $name,
    ) {}
}

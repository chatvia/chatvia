<?php

namespace Domain\Teams\Data;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;

class CreateTeamData extends Data
{
    public function __construct(
        public User $owner,
        public string $name,
        public bool $canAccessDashboard = true,
    ) {}
}

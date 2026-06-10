<?php

namespace Domain\Billing\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class SubscribeMembersData extends Data
{
    public function __construct(
        public User $user,
        public int $quantity,
    ) {}
}

<?php

namespace Domain\Billing\Data;

use App\Workspace\Models\User;
use Spatie\LaravelData\Data;

class SubscribeCustomDomainData extends Data
{
    public function __construct(
        public User $user,
        public int $quantity,
    ) {}
}

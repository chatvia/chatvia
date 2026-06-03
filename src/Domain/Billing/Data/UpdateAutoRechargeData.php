<?php

namespace Domain\Billing\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class UpdateAutoRechargeData extends Data
{
    public function __construct(
        public User $user,
        public bool $enabled,
        public int $threshold,
        public int $amount,
    ) {}
}

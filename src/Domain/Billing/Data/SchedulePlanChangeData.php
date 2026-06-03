<?php

namespace Domain\Billing\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class SchedulePlanChangeData extends Data
{
    public function __construct(
        public User $user,
        public string $package,
        public bool $isYearly,
    ) {}
}

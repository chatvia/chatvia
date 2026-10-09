<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Subscriptions;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Inputs for scheduling a move to another plan. `package` is the plan's name
 * as the plan-change form posts it.
 */
class SchedulePlanChangeData extends Data
{
    public function __construct(
        public User $user,
        public string $package,
        public bool $isYearly,
    ) {}
}

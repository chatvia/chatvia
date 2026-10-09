<?php

namespace Domain\Billing\Data\Account;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Auto-recharge settings: on/off, the balance that triggers a recharge, and
 * how many messages one recharge buys.
 */
class UpdateAutoRechargeData extends Data
{
    public function __construct(
        public User $user,
        public bool $enabled,
        public int $threshold,
        public int $amount,
    ) {}
}

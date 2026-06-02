<?php

namespace Domain\Billing\Data;

use App\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Inputs for `BuyExtraMessageCreditsAction::execute()`. `quantity` is a
 * multiplier of the extra-credits addon price; each unit adds 1,000 messages
 * (see the action's metadata).
 */
class BuyExtraMessageCreditsData extends Data
{
    public function __construct(
        public User $user,
        public int $quantity,
    ) {}
}

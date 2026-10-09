<?php

namespace Domain\Billing\Data\Account;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Account\BuyExtraMessageCreditsAction;
use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Spatie\LaravelData\Data;

/**
 * Inputs for {@see BuyExtraMessageCreditsAction}. `quantity` is the number of
 * extra-message packs; each adds the configured `unit_messages` (1,000).
 */
class BuyExtraMessageCreditsData extends Data
{
    public function __construct(
        public User $user,
        public int $quantity,
        public CheckoutUrlsData $urls,
    ) {}
}

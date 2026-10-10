<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Spatie\LaravelData\Data;

/**
 * Inputs for subscribing a workspace to a plan through Stripe Checkout.
 * `package` is the plan's key (`billing_plans.key`).
 */
class SubscribeData extends Data
{
    public function __construct(
        public User $user,
        public string $package,
        public bool $yearly,
        public CheckoutUrlsData $urls,
    ) {}
}

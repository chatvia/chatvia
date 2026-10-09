<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Domain\Billing\Enums\Addon;
use Spatie\LaravelData\Data;

/**
 * Inputs for buying an add-on subscription, or changing its quantity when the
 * workspace already has it. `quantity` is ignored for yes/no add-ons such as
 * Remove Branding.
 */
class SubscribeAddonData extends Data
{
    public function __construct(
        public User $user,
        public Addon $addon,
        public int $quantity,
        public CheckoutUrlsData $urls,
    ) {}
}

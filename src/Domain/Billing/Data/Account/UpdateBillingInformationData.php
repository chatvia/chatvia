<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Account;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Inputs for saving the workspace's billing details.
 */
class UpdateBillingInformationData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $email,
        public BillingAddressData $address,
    ) {}
}

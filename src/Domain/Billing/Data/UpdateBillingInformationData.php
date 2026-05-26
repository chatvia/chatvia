<?php

namespace Domain\Billing\Data;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;

class UpdateBillingInformationData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $email,
        public BillingAddressData $address,
    ) {}
}

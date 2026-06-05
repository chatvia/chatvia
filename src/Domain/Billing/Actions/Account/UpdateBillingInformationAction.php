<?php

namespace Domain\Billing\Actions\Account;

use Domain\Billing\Data\UpdateBillingInformationData;

class UpdateBillingInformationAction
{
    public function execute(UpdateBillingInformationData $data): void
    {
        $user = $data->user;
        $address = $data->address;

        $user->update([
            'name' => $data->name,
            'email' => $data->email,
        ]);

        // Cashier's `Billable` trait lives on `UserBilling`, not `User`.
        $billing = $user->accountBillingOrFail();
        $billing->update(['billing_address' => $address->toArray()]);
        $billing->updateStripeCustomer([
            'name' => $data->name,
            'email' => $data->email,
            'address' => [
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
            ],
        ]);
    }
}

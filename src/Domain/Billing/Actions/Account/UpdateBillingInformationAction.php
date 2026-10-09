<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Account;

use Domain\Billing\Data\Account\UpdateBillingInformationData;
use Illuminate\Support\Facades\DB;

/**
 * Saves the workspace's billing name, email and address locally and on the
 * Stripe customer.
 */
class UpdateBillingInformationAction
{
    public function execute(UpdateBillingInformationData $data): void
    {
        $user = $data->user;
        $address = $data->address;

        // Cashier's `Billable` trait lives on `UserBilling`, not `User`.
        $billing = $user->accountBillingOrFail();

        DB::transaction(function () use ($user, $billing, $data, $address): void {
            $user->update([
                'name' => $data->name,
                'email' => $data->email,
            ]);

            $billing->update(['billing_address' => $address->toArray()]);
        });

        // Stripe sync stays outside the transaction — an external call must
        // not hold a DB transaction open or roll back the local update.
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

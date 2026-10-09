<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Account;

use Domain\Billing\Data\Account\BuyExtraMessageCreditsData;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Enums\PurchaseType;
use Laravel\Cashier\Checkout;

/**
 * Opens a Stripe Checkout for extra-message packs. The credits are granted by
 * the webhook once the checkout completes, keyed on the checkout session so a
 * redelivered event cannot grant them twice.
 */
class BuyExtraMessageCreditsAction
{
    public function execute(BuyExtraMessageCreditsData $data): Checkout
    {
        $pack = MessageCreditAddon::ExtraMessageCredits;

        return $data->user->accountBillingOrFail()->checkout(
            [$pack->stripePriceId() => $data->quantity],
            [
                'success_url' => $data->urls->successUrl,
                'cancel_url' => $data->urls->cancelUrl,
                'metadata' => [
                    StripeMetadata::ID => $pack->value,
                    StripeMetadata::TYPE => PurchaseType::Addon->value,
                    StripeMetadata::QUANTITY => $data->quantity,
                    StripeMetadata::MESSAGES => $pack->unitMessages() * $data->quantity,
                ],
            ],
        );
    }
}

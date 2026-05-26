<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Auth\Models\User;
use App\Http\Requests\Billing\BuyExtraMessageCreditsRequest;
use Domain\Billing\Actions\BuyExtraMessageCreditsAction;
use Laravel\Cashier\Checkout;

class CheckoutExtraMessagesController
{
    public function __invoke(
        BuyExtraMessageCreditsRequest $request,
        BuyExtraMessageCreditsAction $buy,
    ): Checkout {
        /** @var User $user */
        $user = $request->user();

        return $buy->execute($user, (int) $request->integer('quantity'));
    }
}

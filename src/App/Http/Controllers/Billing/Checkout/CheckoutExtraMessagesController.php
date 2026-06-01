<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Requests\Billing\BuyExtraMessageCreditsRequest;
use Domain\Billing\Actions\Account\BuyExtraMessageCreditsAction;
use Laravel\Cashier\Checkout;

class CheckoutExtraMessagesController
{
    public function __invoke(
        BuyExtraMessageCreditsRequest $request,
        BuyExtraMessageCreditsAction $buy,
    ): Checkout {
        return $buy->execute($request->toData());
    }
}

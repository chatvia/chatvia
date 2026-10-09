<?php

namespace App\Http\Controllers\Billing\Home;

use Domain\Billing\Data\Resources\Subscription\BillingResourceData;
use Illuminate\Http\Request;

/**
 * JSON for the billing page: plan, subscription, pending change and add-ons.
 */
class BillingDataController
{
    public function __invoke(Request $request): BillingResourceData
    {
        return BillingResourceData::fromUser($request->user());
    }
}

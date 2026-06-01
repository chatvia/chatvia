<?php

namespace App\Http\Controllers\Billing\Home;

use App\Billing\Data\Resources\Subscription\BillingResourceData;
use Illuminate\Http\Request;

class BillingDataController
{
    public function __invoke(Request $request): BillingResourceData
    {
        return BillingResourceData::fromUser($request->user());
    }
}

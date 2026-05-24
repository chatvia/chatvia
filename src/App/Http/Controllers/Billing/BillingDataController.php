<?php

namespace App\Http\Controllers\Billing;

use App\Billing\Data\Resources\BillingResourceData;
use Illuminate\Http\Request;

class BillingDataController
{
    public function __invoke(Request $request): BillingResourceData
    {
        return BillingResourceData::fromUser($request->user());
    }
}

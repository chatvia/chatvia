<?php

namespace App\Http\Controllers\Billing;

use App\Data\Resources\Billing\BillingResourceData;
use Illuminate\Http\Request;

class BillingDataController
{
    public function __invoke(Request $request): BillingResourceData
    {
        return BillingResourceData::fromUser($request->user());
    }
}

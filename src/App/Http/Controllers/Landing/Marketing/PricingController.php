<?php

namespace App\Http\Controllers\Landing\Marketing;

use App\Http\Controllers\Landing\Concerns\SharesPricingData;
use Domain\Billing\Services\PlanCatalog;
use Illuminate\Contracts\View\View;

/**
 * The public pricing page.
 */
class PricingController
{
    use SharesPricingData;

    public function __invoke(PlanCatalog $plans): View
    {
        return view('landing.pricing', $this->pricingData($plans));
    }
}

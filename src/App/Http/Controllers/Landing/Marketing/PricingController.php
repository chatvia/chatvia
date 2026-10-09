<?php

namespace App\Http\Controllers\Landing\Marketing;

use App\Http\Controllers\Landing\Concerns\SharesPricingData;
use Domain\Billing\Services\PlanCatalog;
use Illuminate\Contracts\View\View;

/**
 * Renders the public pricing page. Plan names, prices and features come from
 * {@see PlanCatalog}, so the page always matches the plans that can be bought.
 */
class PricingController
{
    use SharesPricingData;

    public function __invoke(PlanCatalog $plans): View
    {
        return view('landing.pricing', $this->pricingData($plans));
    }
}

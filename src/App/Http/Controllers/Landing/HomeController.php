<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Landing\Concerns\SharesPricingData;
use Domain\Billing\Services\PlanCatalog;
use Illuminate\Contracts\View\View;

/**
 * The public home page, with the pricing section.
 */
class HomeController
{
    use SharesPricingData;

    public function __invoke(PlanCatalog $plans): View
    {
        return view('landing.index', [...$this->pricingData($plans), 'demo' => false]);
    }
}

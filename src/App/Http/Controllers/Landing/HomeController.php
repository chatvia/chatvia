<?php

namespace App\Http\Controllers\Landing;

use Domain\Billing\Services\Billing;
use Domain\Llm\Models\Llm;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class HomeController
{
    public function __invoke(Billing $billing): Factory|View
    {
        return view('landing.index', [
            'landing' => [],
            'demo' => false,
            'pricing' => true,
            'packages' => $billing->getAvailablePackages(),
            'models' => Llm::query()
                ->orderBy('descriptive_name')
                ->get(['descriptive_name', 'icon'])
                ->map(fn (Llm $llm): array => ['name' => $llm->descriptive_name, 'icon' => $llm->icon])
                ->all(),
            'billingConfig' => [
                'ui' => config('billing.ui'),
                'currencySymbol' => config('billing.currency_symbol'),
                'discount' => config('billing.discount'),
                'commonFeatures' => config('billing.common_features'),
            ],
        ]);
    }
}

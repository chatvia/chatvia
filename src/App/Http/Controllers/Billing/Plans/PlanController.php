<?php

namespace App\Http\Controllers\Billing\Plans;

use App\Billing\Services\Billing;
use Domain\Llm\Models\Llm;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController
{
    public function __invoke(Request $request, Billing $billing)
    {
        $currentPackage = $request->user()->getCurrentPackage();

        $llms = Llm::query()->select(['descriptive_name', 'icon'])->get();

        return Inertia::render('billing/Plans', [
            'packages' => $billing->getAvailablePackages()->values()->toArray(),
            'currentPackage' => $currentPackage['id'] ?? null,
            'comparisonFeatures' => array_values($billing->comparisonFeatures()),
            'billingConfig' => [
                'ui' => config('billing.ui'),
                'commonFeatures' => config('billing.common_features'),
                'packageIcons' => config('billing.package_icons'),
                'currencySymbol' => config('billing.currency_symbol'),
                'trial' => config('billing.trial'),
                'discount' => config('billing.discount'),
            ],
            'llms' => $llms->map(function ($llm) {
                return [
                    'name' => $llm->descriptive_name,
                    'icon' => $llm->icon,
                ];
            }),
        ]);
    }
}

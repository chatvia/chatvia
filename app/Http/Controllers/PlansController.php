<?php

namespace App\Http\Controllers;

use App\Models\Llm;
use App\Support\Billing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlansController
{
    public function __invoke(Request $request, Billing $billing)
    {
        $currentPackage = $request->user()->getCurrentPackage();

        $addons = collect(config('billing.addons'))->map(function ($addon, $key) {
            return array_merge($addon, ['id' => $key]);
        })->values();

        $llms = Llm::query()->select(['descriptive_name', 'icon', 'requires_paid_plan'])->get();

        return Inertia::render('billing/Plans', [
            'packages' => $billing->getAvailablePackages(),
            'addons' => $addons,
            'currentPackage' => $currentPackage['id'],
            'comparisonFeatures' => $billing->comparisonFeatures(),
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
                    'paid' => $llm->requires_paid_plan,
                ];
            }),
        ]);
    }
}

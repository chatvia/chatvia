<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackagesController
{
    public function __invoke(Request $request, Billing $billing)
    {
        $currentPackage = $request->user()->getCurrentPackage();

        $addons = collect(config('billing.addons'))->map(function ($addon, $key) {
            return array_merge($addon, ['id' => $key]);
        })->values();

        return Inertia::render('billing/Packages', [
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
            ],
        ]);
    }
}

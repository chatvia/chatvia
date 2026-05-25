<?php

namespace App\Http\Controllers;

use App\Billing\Services\Billing;
use App\Hosting\HostConfig;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PricingController
{
    public function __invoke(Request $request, Billing $billing): Factory|View
    {
        $config = HostConfig::get($request->host());

        return view('landing.pricing', [
            'landing' => $config['landing'],
            'pricing' => true,
            'packages' => $billing->getAvailablePackages(),
            'billingConfig' => [
                'ui' => config('billing.ui'),
                'currencySymbol' => config('billing.currency_symbol'),
                'discount' => config('billing.discount'),
                'commonFeatures' => config('billing.common_features'),
            ],
        ]);
    }
}

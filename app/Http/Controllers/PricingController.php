<?php

namespace App\Http\Controllers;

use App\Services\Billing\Billing;
use App\Services\HostConfig;
use Illuminate\Http\Request;

class PricingController
{
    public function __invoke(Request $request, Billing $billing)
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
            ],
        ]);
    }
}

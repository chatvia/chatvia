<?php

namespace App\Http\Controllers;

use App\Services\Billing\Billing;
use App\Services\HostConfig;
use Illuminate\Http\Request;

class HomeController
{
    public function __invoke(Request $request, Billing $billing)
    {
        $config = HostConfig::get($request->host());

        return view('landing.index', [
            'landing' => $config['landing'],
            'demo' => $config['user']['demo'],
            'pricing' => $config['pricing']['show'],
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

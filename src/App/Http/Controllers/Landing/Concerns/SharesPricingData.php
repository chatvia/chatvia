<?php

namespace App\Http\Controllers\Landing\Concerns;

use Domain\Billing\Services\PlanCatalog;
use Domain\Llm\Models\Llm;

/**
 * The data the pricing section needs, shared by the home page and the
 * pricing page: the plans on sale, the models on offer and the billing
 * display settings.
 */
trait SharesPricingData
{
    /**
     * @return array<string, mixed>
     */
    protected function pricingData(PlanCatalog $plans): array
    {
        return [
            'landing' => [],
            'pricing' => true,
            'packages' => $plans->availablePackages(),
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
        ];
    }
}

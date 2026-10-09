<?php

namespace App\Http\Controllers\Billing\Plans;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Services\PlanCatalog;
use Domain\Llm\Models\Llm;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The in-app plan picker: every plan, the one the account is on, and the
 * pricing-page settings from `config('billing')`.
 */
class PlanController
{
    public function __invoke(Request $request, PlanCatalog $plans): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('billing/Plans', [
            'packages' => $plans->availablePackages()->values()->toArray(),
            'currentPackage' => $user->getCurrentPackage()['id'] ?? null,
            'billingConfig' => [
                'ui' => config('billing.ui'),
                'commonFeatures' => config('billing.common_features'),
                'packageIcons' => config('billing.package_icons'),
                'currencySymbol' => config('billing.currency_symbol'),
                'trial' => config('billing.trial'),
                'discount' => config('billing.discount'),
            ],
            'llms' => Llm::query()
                ->select(['descriptive_name', 'icon'])
                ->get()
                ->map(fn (Llm $llm): array => ['name' => $llm->descriptive_name, 'icon' => $llm->icon]),
        ]);
    }
}

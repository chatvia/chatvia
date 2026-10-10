<?php

namespace App\Http\Controllers\Billing\Plans;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Services\PlanCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The in-app plan picker: every plan a workspace can choose, the one the
 * account is on, the plan icons and the pricing-page settings.
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
                'packageIcons' => $plans->packageIcons(),
                'currencySymbol' => config('billing.currency_symbol'),
                'trial' => config('billing.trial'),
                'discount' => config('billing.discount'),
            ],
        ]);
    }
}

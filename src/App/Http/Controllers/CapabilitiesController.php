<?php

namespace App\Http\Controllers;

use App\Billing\Data\Resources\BillingPackageCapabilitiesData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapabilitiesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $capabilities = BillingPackageCapabilitiesData::forPlan($user->plan);

        return response()->json([
            'plan' => $user->plan,
            'capabilities' => $capabilities,
        ]);
    }
}
